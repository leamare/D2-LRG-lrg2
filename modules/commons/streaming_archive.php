<?php

/**
 * Streaming tar.gz archive implementation that avoids creating temporary files
 * Similar to the built-in PharData class, but without the temporary tar container
 */

class StreamingArchive {
    private $gz;
    private $current_file = null;
    private $current_file_size = 0;
    private $current_file_pos = 0;
    private $current_file_name = '';
    private $current_file_mode = 0;
    private $current_file_mtime = 0;
    private $current_file_uid = 0;
    private $current_file_gid = 0;
    private $buffer_size;
    private $compression_level;

    public function __construct($output_path, $compression_level = 9, $buffer_size = 65536) {
        $this->buffer_size = $buffer_size;
        $this->compression_level = max(1, min(9, $compression_level));
        
        $this->gz = gzopen($output_path, 'wb' . $this->compression_level);
        if (!$this->gz) {
            throw new Exception("Could not initialize gzip compression");
        }
    }

    public function addFile($name, $real_path) {
        if (!file_exists($real_path)) {
            throw new Exception("File does not exist: $real_path");
        }

        $stat = stat($real_path);
        if (!$stat) {
            throw new Exception("Could not stat file: $real_path");
        }

        $this->current_file = fopen($real_path, 'rb');
        if (!$this->current_file) {
            throw new Exception("Could not open file for reading: $real_path");
        }

        $this->current_file_name = $name;
        $this->current_file_size = $stat['size'];
        $this->current_file_mode = $stat['mode'] & 0x0FFF;
        $this->current_file_mtime = $stat['mtime'];
        $this->current_file_uid = $stat['uid'];
        $this->current_file_gid = $stat['gid'];
        $this->current_file_pos = 0;

        $this->writeHeader();
        $this->writeFileContent();
        $this->writePadding();

        fclose($this->current_file);
        $this->current_file = null;
    }

    private function writeHeader() {
        // Create header with proper tar format
        $header = '';
        $header .= str_pad(substr($this->current_file_name, 0, 100), 100, "\0"); // name (100 bytes)
        $header .= str_pad(sprintf('%07o', $this->current_file_mode), 8, "\0"); // mode (8 bytes)
        $header .= str_pad(sprintf('%07o', $this->current_file_uid), 8, "\0"); // uid (8 bytes)
        $header .= str_pad(sprintf('%07o', $this->current_file_gid), 8, "\0"); // gid (8 bytes)
        $header .= str_pad(sprintf('%011o', $this->current_file_size), 12, "\0"); // size (12 bytes)
        $header .= str_pad(sprintf('%011o', $this->current_file_mtime), 12, "\0"); // mtime (12 bytes)
        $header .= str_repeat(' ', 8); // chksum placeholder (8 bytes)
        $header .= '0'; // typeflag (1 byte)
        $header .= str_repeat("\0", 100); // linkname (100 bytes)
        $header .= 'ustar'; // magic (6 bytes)
        $header .= '00'; // version (2 bytes)
        $header .= str_repeat("\0", 32); // uname (32 bytes)
        $header .= str_repeat("\0", 32); // gname (32 bytes)
        $header .= str_repeat("\0", 8); // devmajor (8 bytes)
        $header .= str_repeat("\0", 8); // devminor (8 bytes)
        $header .= str_repeat("\0", 155); // prefix (155 bytes)
        $header .= str_repeat("\0", 12); // padding (12 bytes)

        // Ensure header is exactly 512 bytes
        $header_len = strlen($header);
        if ($header_len !== 512) {
            // Calculate what we have: 100+8+8+8+12+12+8+1+100+6+2+32+32+8+8+155+12 = 510 bytes
            // We need 2 more bytes to reach 512
            $header .= str_repeat("\0", 512 - $header_len);
        }

        // Calculate checksum
        $checksum = 0;
        for ($i = 0; $i < 512; $i++) {
            $checksum += ord($header[$i]);
        }
        
        // Replace checksum field
        $checksum_str = sprintf('%07o', $checksum) . ' ';
        $header = substr_replace($header, $checksum_str, 148, 8);

        gzwrite($this->gz, $header);
    }

    private function writeFileContent() {
        while ($this->current_file_pos < $this->current_file_size) {
            $remaining = $this->current_file_size - $this->current_file_pos;
            $to_read = min($this->buffer_size, $remaining);
            $data = fread($this->current_file, $to_read);
            if ($data === false) {
                throw new Exception("Error reading file content");
            }
            gzwrite($this->gz, $data);
            $this->current_file_pos += strlen($data);
        }
    }

    private function writePadding() {
        $padding = 512 - ($this->current_file_size % 512);
        if ($padding < 512) {
            gzwrite($this->gz, str_repeat("\0", $padding));
        }
    }

    public function close() {
        if ($this->gz) {
            // Write two empty blocks to mark end of archive
            gzwrite($this->gz, str_repeat("\0", 1024));
            gzclose($this->gz);
            $this->gz = null;
        }
    }

    public function __destruct() {
        $this->close();
    }
} 