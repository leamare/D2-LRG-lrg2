$(document).ready(function() {
  $('.list tbody tr').on('click', function() {
    $(this).toggleClass('highlighted');
  });

  $('table.sortable').tablesorter({
    sortInitialOrder: 'desc',
    stringTo: 'min',
    sortReset: true,
  });
  
  $(".search-filter").on("keyup", function() {
    const value = $(this).val().toLowerCase();
    const table = $(this).attr('data-table-filter-id');
    
    $("#" + table + " tbody tr").filter(function() {
      const aliases = $(this).find('img[data-aliases], a[data-aliases]').attr('data-aliases');
      const line = $(this).text().toLowerCase() + (aliases ? ' ' + aliases.toLowerCase() : '');
      $(this).toggle( line.indexOf(value) !== -1 )
    });
  });
});


// code from example on w3c

function showModal(text, cap) {
    document.getElementById('modal-text').innerHTML = text;
    document.getElementsByClassName('modal-header')[0].innerHTML = cap;
    document.getElementById('modal-box').style.display = "block";
}
window.onclick = function(event) {
    if (event.target == document.getElementById('modal-box')) {
        document.getElementById('modal-box').style.display = "none";
        document.getElementById('modal-sublevel').style.display = "none";
    }
}

function expandSubModal(text) {
    document.getElementById('modal-sublevel').innerHTML = text;
    document.getElementById('modal-sublevel').style.display = "block";
}
document.getElementById('modal-text').onclick = function(event) {
    if (event.target == document.getElementById('modal-text')) {
        document.getElementById('modal-sublevel').style.display = "none";
    }
}

function switchTab(evt, moduleID, className) {
    var i, tabcontent, tablinks;
    parent = document.getElementById(moduleID).parentNode;

    tabcontent = parent.getElementsByClassName(className);
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].className = tabcontent[i].className.replace(" active", "");
    }

    tablinks = parent.getElementsByClassName(className+"-selector");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    // Show the current tab, and add an "active" class to the button that opened the tab
    document.getElementById(moduleID).className += " active";
    evt.currentTarget.className += " active";
}


function select_modules_link(a) {
  if(a.value)
    window.location=a.value;
}



$(".tagsshow .category").on("click", () => {
    if ($(".tagslist").hasClass("hidden")) {
        $(".tagslist").toggleClass("hidden");
        $(".tagslist").slideDown();
    } else {
        $(".tagslist").slideUp(300, () => $(".tagslist").toggleClass("hidden"));
    }
});

function setCookie(name, value, options = {}) {
    options = {
      path: '/',
      ...options
    };
  
    if (options.expires instanceof Date) {
      options.expires = options.expires.toUTCString();
    }
  
    let updatedCookie = encodeURIComponent(name) + "=" + encodeURIComponent(value);
  
    for (let optionKey in options) {
      updatedCookie += "; " + optionKey;
      let optionValue = options[optionKey];
      if (optionValue !== true) {
        updatedCookie += "=" + optionValue;
      }
    }
  
    document.cookie = updatedCookie;
}

function setLocale(loc) {
    setCookie('loc', loc, { 'max-age': 86400 * 90 });
    location.reload();
}