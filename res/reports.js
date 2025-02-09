function parseHashParams() {
  let params = {};
  window.location.hash.substring(1).split('&').forEach(v => {
    v = v.split('=');
    params[v[0]] = v[1] ? decodeURI(v[1].replace('%26', '&')) : null;
  });

  return params;
}

function setHashParam(key, value = null) {
  let params = parseHashParams();

  if (!value) delete params[key];
  else {
    params[key] = value.replace('&', '%26');
  }

  let link = [];
  for (let k in params) {
    if (!k) continue;
    link.push(k + '=' + params[k]);
  }

  if (!link.length) removeHash();
  else window.location.hash = link.length ? '#'+link.join('&') : '';
}

function removeHash() { 
  history.pushState('', document.title, window.location.pathname + window.location.search);
}

$(document).ready(function() {
  $('.list tbody tr, .flextable .line').on('click', function() {
    $(this).toggleClass('highlighted');
  });

  $('.flextable .expandable .expand, table .expandable .expand').on('click', function() {
    let parent = $(this).parent().parent();
    parent.toggleClass('closed');
    let rows = parent.parent().find('tr[data-group='+$(parent).attr('data-group')+']');
    for (i=0; i<rows.length; i++) {
      $(rows[i]).toggleClass('collapsed');
    }
  });

  $('.table-column-toggles').each(function() {
    const toggles = this;
    const table = $(this).attr('data-table');

    $(this).find('input[type=checkbox]').each(function() {
      $(this).prop('checked', true);
      $(this).on('change', function() {
        const group = $(this).attr('data-group');
        const status = this.checked;
        $('#'+table+' td[data-col-group='+group+'], #'+table+' th[data-col-group='+group+']').each(function() {
          $(this).toggle(status);
        });
      });
    });

    let tableWidth = $('#'+table).width();
    const isWide = $('#'+table).hasClass('wide');
    const greedy = $(this).hasClass('greedy');
    const tableContainerWidth = $('#'+table).parent().width() * (isWide ? .98 : .82) * (greedy ? 1.05 : .875);

    if (tableContainerWidth < tableWidth) {
      let overhead = [];
      $('#'+table+' tr.overhead th').each(function() {
        const group = $(this).attr('data-col-group');
        if (!group || group == '_index') return;

        const toggle = $(toggles).find('input[data-group='+group+']')[0];
        const priority = $(toggle).attr('data-group-priority');
        if (!priority) return;

        const width = $(this).width();
        overhead.push({
          group,
          width,
          priority,
          toggle,
        });
      });

      overhead.sort((a, b) => {
        if (a.priority != b.priority) {
          return b.priority - a.priority
        }

        return b.width - a.width;
      });

      for (const i in overhead) {
        $(overhead[i].toggle).prop('checked', false);
        $(overhead[i].toggle).trigger('change');
        tableWidth -= overhead[i].width;
        if (tableWidth <= tableContainerWidth) break;
      }
    }
  });

  $('table.sortable').tablesorter({
    sortInitialOrder: 'desc',
    stringTo: 'min',
    sortReset: true,
  });
  
  $(".search-filter").on("input", function() {
    const table = $(this).attr('data-table-filter-id');
    let filter = $(this).val().toLowerCase();

    setHashParam('sf-'+table, filter);

    filter = filter.split('&');
    filter = filter.map(f => f.replace(/(\s|^)\-(.*?)(\s|$)/g, '$1(^(?!.*($2)).*)$3'));
    
    const value = filter.map(f => new RegExp(f.replace(/^\s+|\s+$/g, '')));

    let rowGroups = {};
    
    $("#" + table + " tbody tr").each(function() {
      let group = $(this).attr('data-group');
      if (!group) {
        if ($(this).hasClass('secondary')) {
          group = Object.keys(rowGroups)[ Object.keys(rowGroups).length-1 ];
        } else {
          group = table+"_group_"+Object.keys(rowGroups).length;
        }
      }
      if (!rowGroups[group]) rowGroups[group] = [];
      rowGroups[group].push(this);
    });

    for (const group in rowGroups) {
      let line = "";
      for (const i in rowGroups[group]) {
        const el = rowGroups[group][i];
        const aliasesRaw = $(el).find('img[data-aliases], a[data-aliases]');
        let aliases = $(el).attr('data-aliases');
        if (aliasesRaw.length) {
          for (let i=0; i<aliasesRaw.length; i++) {
            aliases += ' ' + $(aliasesRaw[i]).attr('data-aliases');
          }
        }

        line +=  $(el).text().toLowerCase() + (aliases ? ' ' + aliases.toLowerCase() : '') + ' ';
      }
      
      let visible = true;
      for (const i in value) {
        visible = visible && line.match(value[i]) !== null;
        if (!visible) break;
      }

      for (const i in rowGroups[group]) {
        $(rowGroups[group][i]).toggle( visible );
      }
    }
  });

  let selectors = $('.custom-selector');
  let ssz = selectors.length;

  for (let si = 0; si < ssz; si++) {
    let el = selectors[si];

    let select = $(el).find('select');
    let div = $( '<div class="custom-selector-selected"></div>' );
    $(div).on('click', function(e) {
      let isOpen = !$(this).parent().hasClass('select-show');
      // closeAllSelect($(this).parent());
      $(this).parent().toggleClass('select-show');

      let container = $(this).parent().find('.custom-selector-values')[0];
      if (isOpen) {
        $(this).siblings('input.search-filter-selector').focus();
        
        setTimeout(() => {
          let selected = $(container).find('.custom-selector-option.as-selected');
          
          if (!selected.length) {
            selected = $(container).find('.custom-selector-option:visible');
          }

          if (selected.length) {
            scrollTo(container, selected[0]);
          }
        }, 150);
      } else {
        $(container).find('.focused').toggleClass('focused');
      }
      e.stopPropagation();
    });

    const selected = $(select).find('option:selected');
    const icon = $(selected).attr('data-icon');
    const selectedValue = selected.html();
    
    $(div).html( selectedValue );
    if (icon) {
      $(div).prepend( $('<img>', { src: icon, class: 'custom-selector-option-icon' }) );
    }
    $(el).append(div);

    div = $( '<div class="custom-selector-values custom-selector-list"></div>' );
    let options = select.children();
    let sz = options.length;

    for(let i=0; i<sz; i++) {
      const option = $( '<div class="custom-selector-option"></div>' );
      const icon = $(options[i]).attr('data-icon');
      const value = $(options[i]).html();
      $(option).html(value);
      $(option).attr( 'data-aliases', $(options[i]).attr('data-aliases') );
      $(option).attr( 'data-value', value );
      if (icon) {
        $(option).prepend( $('<img>', { src: icon, class: 'custom-selector-option-icon' }) );
      } else {
        $(option).addClass('no-icon');
      }
      if (selectedValue == value) $(option).addClass('as-selected');

      $(option).on('click', function (e) {
        const value = $(this).attr('data-value');
        const select = $(this).parent().parent().find('select')[0];
        const cselected = $(this).parent().parent().find('.custom-selector-selected')[0];
        const sl = select.length;
        let link;

        for (i = 0; i < sl; i++) {
          if ($(select[i]).html() == value) {
            select.selectedIndex = i;
            $(cselected).html(value);
            let icon = $(options[i]).attr('data-icon');
            if (icon) {
              $(cselected).prepend( $('<img>', { src: icon, class: 'custom-selector-option-icon' }) );
            }

            link = $(select[i]).attr('value');

            let asSelected = $(this).parent('.as-selected');
            let sz = asSelected.length;
            for (j = 0; j < sz; j++) {
              $(asSelected[j]).removeClass('as-selected');
            }
            $(this).addClass('as-selected');

            break;
          }
        }

        closeAllSelect();
        e.stopPropagation();

        $(select).trigger('change');
      });

      $(option).on('mouseenter', function (e) {
        $(this).parent().find('.focused').toggleClass('focused');
        $(this).toggleClass('focused');
      });

      $(div).append(option);
    }

    $(el).append( div );

    if (sz > 10) {
      const placeholder = $(select).attr('data-placeholder');
      const input = $(`<input type="search" name="filter" class="search-filter-selector" placeholder="${placeholder}" />`);

      $(input).on('input', function() {
        const value = new RegExp( $(this).val().toLowerCase() );
        const options = $(this).parent().find('.custom-selector-list')[0];
        
        $(options).children('.custom-selector-option').filter(function() {
          const aliases = $(this).attr('data-aliases');
          const line = $(this).text().toLowerCase() + (aliases ? ' ' + aliases.toLowerCase() : '');
          $(this).toggle( line.match(value) !== null )
        });

        $(options).find('.focused').toggleClass('focused');
        $( $(options).children('.custom-selector-option:visible')[0] ).toggleClass('focused');
      });

      $(input).on('click', function(e) {
        e.stopPropagation();
      });

      $(input).insertBefore( div );

      $(el).addClass('searchable');
    }

    $(el).on('keydown', function(e) {
      if ( $(this).hasClass('select-show') ) {
        if (e.keyCode == 40) { // arrowdown
          let container = $(this).find('.custom-selector-values')[0];
          let selected = $(container).find('.custom-selector-option.focused:visible');
          if (selected.length) {
            let next = $(selected[0]).nextAll(':visible');
            if (next.length) {
              $(container).find('.custom-selector-option.focused').toggleClass('focused');
              $( next[0] ).toggleClass('focused', true);
              scrollTo(container, next[0]);
            }
          } else {
            selected = $(container).find('.custom-selector-option.as-selected:visible');
            if (!selected.length) selected = $(container).find('.custom-selector-option:visible');
            $(selected[0]).toggleClass('focused', true);

            scrollTo(container, selected[0]);
          }

          e.stopPropagation();
        }

        if (e.keyCode == 38) { // arrowup
          let container = $(this).find('.custom-selector-values')[0];
          let selected = $(container).find('.custom-selector-option.focused:visible');
          if (selected.length) {
            let prev = $(selected[0]).prevAll(':visible');
            if (prev.length) {
              $(container).find('.custom-selector-option.focused').toggleClass('focused');
              $( prev[0] ).toggleClass('focused', true);
              scrollTo(container, prev[0]);
            }
          } else {
            selected = $(container).find('.custom-selector-option.as-selected:visible');
            if (!selected.length) selected = $(container).find('.custom-selector-option:visible');
            $(selected[0]).toggleClass('focused', true);

            scrollTo(container, selected[0]);
            
          }

          e.stopPropagation();
        }

        if (e.keyCode == 13) { // enter
          let selected = $(this).find('.custom-selector-option.focused:visible');
          if (selected.length) { 
            $(selected[0]).trigger('click');
          }

          e.stopPropagation();
        }
      }
    });
  }

  selectors = $('.custom-selector-multiple');
  ssz = selectors.length;

  for (let si = 0; si < ssz; si++) {
    let el = selectors[si];

    let select = $(el).find('select');
    let div = $( '<div class="custom-selector-selected"></div>' );
    let selectedValue = [];

    $(div).on('click', function(e) {
      let isOpen = !$(this).parent().hasClass('select-show');
      // closeAllSelect($(this).parent());
      $(this).parent().toggleClass('select-show');

      let container = $(this).parent().find('.custom-selector-values')[0];
      if (isOpen) {
        $(this).siblings('input.search-filter-selector').focus();
        
        setTimeout(() => {
          let selected = $(container).find('.custom-selector-option.as-selected');
          
          if (!selected.length) {
            selected = $(container).find('.custom-selector-option:visible');
          }

          if (selected.length) {
            scrollTo(container, selected[0]);
          }
        }, 125);
      } else {
        $(container).find('.focused').toggleClass('focused');
      }
      e.stopPropagation();
    });

    $(select).on('change', function() {
      const cselected = $(this).parent().find('.custom-selector-selected')[0];
      const options = select.children();
      const sz = options.length;
      let values = [];
      let icons = [];

      for(let i=0; i<sz; i++) {
        if (options[i].selected) {
          const icon = $(options[i]).attr('data-icon');
          values.push( $(options[i]).html() );
          if (icon) icons.unshift( icon );
        }
      }

      $(cselected).html( values.length ? values.join(', ') : $(this).attr('data-empty-placeholder') );

      if (icons.length) {
        for (let i in icons) {
          $(cselected).prepend( $('<img>', { src: icons[i], class: 'custom-selector-option-icon' }) );
        }
      }
    });

    let icons = [];

    let optdiv = $( '<div class="custom-selector-values custom-selector-list"></div>' );
    let options = select.children();
    let sz = options.length;

    for(let i=0; i<sz; i++) {
      const option = $( '<div class="custom-selector-option"></div>' );
      const icon = $(options[i]).attr('data-icon');
      const value = $(options[i]).html();

      if (options[i].selected) {
        selectedValue.push(value);
        if (icon) icons.push(icon);
      }

      $(option).html(value);
      $(option).attr( 'data-aliases', $(options[i]).attr('data-aliases') );
      $(option).attr( 'data-value', value );
      if (icon) {
        $(option).prepend( $('<img>', { src: icon, class: 'custom-selector-option-icon' }) );
      } else {
        $(option).addClass('no-icon');
      }
      if (options[i].selected) $(option).addClass('as-selected');

      $(option).on('click', function (e) {
        const value = $(this).attr('data-value');
        const select = $(this).parent().parent().find('select')[0];
        const cselected = $(this).parent().parent().find('.custom-selector-selected')[0];
        const sl = select.length;
        let link;

        for (i = 0; i < sl; i++) {
          if ($(select[i]).html() == value) {
            select[i].selected = !select[i].selected;
            $(this).toggleClass('as-selected');

            $(cselected).html(value);
            let icon = $(options[i]).attr('data-icon');
            if (icon) {
              $(cselected).prepend( $('<img>', { src: icon, class: 'custom-selector-option-icon' }) );
            }
          }
        }

        $(select).trigger('change');
      });

      $(option).on('mouseenter', function (e) {
        $(this).parent().find('.focused').toggleClass('focused');
        $(this).toggleClass('focused');
      });

      $(optdiv).append(option);
    }

    $(el).append( div );
  
    $(el).append( optdiv );

    $(select).trigger('change');

    if (sz > 10) {
      const placeholder = $(select).attr('data-placeholder');
      const input = $(`<input type="search" class="search-filter-selector" placeholder="${placeholder}" />`);

      $(input).on('input', function() {
        const value = new RegExp( $(this).val().toLowerCase() );
        const options = $(this).parent().find('.custom-selector-list')[0];
        
        $(options).children('.custom-selector-option').filter(function() {
          const aliases = $(this).attr('data-aliases');
          const line = $(this).text().toLowerCase() + (aliases ? ' ' + aliases.toLowerCase() : '');
          $(this).toggle( line.match(value) !== null )
        });

        $(options).find('.focused').toggleClass('focused');
        $( $(options).children('.custom-selector-option:visible')[0] ).toggleClass('focused');
      });

      $(input).on('click', function(e) {
        e.stopPropagation();
      });

      $(input).insertBefore( div );

      $(el).addClass('searchable');
    }

    $(el).on('keydown', function(e) {
      if ( $(this).hasClass('select-show') ) {
        if (e.keyCode == 40) { // arrowdown
          let container = $(this).find('.custom-selector-values')[0];
          let selected = $(container).find('.custom-selector-option.focused:visible');
          if (selected.length) {
            let next = $(selected[0]).nextAll(':visible');
            if (next.length) {
              $(container).find('.custom-selector-option.focused').toggleClass('focused');
              $( next[0] ).toggleClass('focused', true);
              scrollTo(container, next[0]);
            }
          } else {
            selected = $(container).find('.custom-selector-option.as-selected:visible');
            if (!selected.length) selected = $(container).find('.custom-selector-option:visible');
            $(selected[0]).toggleClass('focused', true);

            scrollTo(container, selected[0]);
          }

          e.stopPropagation();
        }

        if (e.keyCode == 38) { // arrowup
          let container = $(this).find('.custom-selector-values')[0];
          let selected = $(container).find('.custom-selector-option.focused:visible');
          if (selected.length) {
            let prev = $(selected[0]).prevAll(':visible');
            if (prev.length) {
              $(container).find('.custom-selector-option.focused').toggleClass('focused');
              $( prev[0] ).toggleClass('focused', true);
              scrollTo(container, prev[0]);
            }
          } else {
            selected = $(container).find('.custom-selector-option.as-selected:visible');
            if (!selected.length) selected = $(container).find('.custom-selector-option:visible');
            $(selected[0]).toggleClass('focused', true);

            scrollTo(container, selected[0]);
            
          }

          e.stopPropagation();
        }

        if (e.keyCode == 13) { // enter
          let selected = $(this).find('.custom-selector-option.focused:visible');
          if (selected.length) { 
            $(selected[0]).trigger('click');
          }

          e.stopPropagation();
        }
      }
    });
  }

  $(".filter-toggle").on("change", function() {
    const group = $(this).attr('data-filter-group');

    if (!group) {
      const table = $(this).attr('data-table');
      const param = $(this).attr('data-param');
      const value = +$(this).attr('data-value');
      const status = this.checked;

      let rowGroups = {};
    
      $("#" + table + " tbody tr").each(function() {
        let group = $(this).attr('data-group');
        if (!group) {
          if ($(this).hasClass('secondary')) {
            group = Object.keys(rowGroups)[ Object.keys(rowGroups).length-1 ];
          } else {
            group = table+"_group_"+Object.keys(rowGroups).length;
          }
        }
        if (!rowGroups[group]) rowGroups[group] = [];
        rowGroups[group].push(this);
      });

      for (const group in rowGroups) {
        let value = null;
        for (const i in rowGroups[group]) {
          const el = rowGroups[group][i];
          if ($(el).attr(param) !== undefined) {
            value = $(this).attr(param);
            break;
          }
        }
        
        const visible = !(status ? +$(this).attr(param) >= value : true);
  
        for (const i in rowGroups[group]) {
          $(rowGroups[group][i]).toggleClass('filter-toggle-hidden', visible );
        }
      }
    } else {
      const filters = $(`.filter-toggle[data-filter-group=${group}]`);

      let tables = {};

      for (let i = 0; i < filters.length; i++) {
        const table = $(filters[i]).attr('data-table');
        if (!table) continue;

        if (!tables[table]) {
          tables[table] = {};
        }

        tables[table][ filters[i].id ] = {
          param: $(filters[i]).attr('data-param'),
          value: +$(filters[i]).attr('data-value'),
          status: filters[i].checked
        };
      }

      for (let table in tables) {
        let rowGroups = {};

        $("#" + table + " tbody tr").each(function() {
          let group = $(this).attr('data-group');
          if (!group) {
            if ($(this).hasClass('secondary')) {
              group = Object.keys(rowGroups)[ Object.keys(rowGroups).length-1 ];
            } else {
              group = table+"_group_"+Object.keys(rowGroups).length;
            }
          }
          if (!rowGroups[group]) rowGroups[group] = [];
          rowGroups[group].push(this);
        });

        for (const group in rowGroups) {
          let value = true;
          for (const i in rowGroups[group]) {
            const el = rowGroups[group][i];

            filterLoop: for (let filter in tables[table]) {
              const filterEl = tables[table][filter];

              if ($(el).attr(filterEl.param) === undefined) continue;

              value = value && (filterEl.status ? +$(el).attr(filterEl.param) >= filterEl.value : true);

              if (!value) break filterLoop;
            }
          }
    
          for (const i in rowGroups[group]) {
            $(rowGroups[group][i]).toggleClass('filter-toggle-hidden', !value );
          }
        }
      }
    }
  });

  $(document).on('click', closeAllSelect);

  const hashParams = parseHashParams();
  for (let k in hashParams) {
    if (!k) continue;
    if (k.indexOf('sf') === 0) {
      const table = k.substring(3);
      $(`input[data-table-filter-id=${table}`).val(hashParams[k]).trigger('input');
    }
  }
});


function scrollTo(container, elem) {
  const margin = parseInt( $(container).css('marginTop') );
  const top = $(elem).position().top;
  const height = $(elem).height();
  const containerHeight = $(container).height();

  if (top+height > containerHeight) {
    $(container).scrollTop(
      $(container).scrollTop() + ( top+height*1.5 - containerHeight )
    );
  } else if (top < 0) {
    $(container).scrollTop(
      $(container).scrollTop() + top
    );
  }
}

function showModal(text, cap) {
    document.getElementById('modal-text').innerHTML = text;
    document.getElementsByClassName('modal-header')[0].innerHTML = cap;
    document.getElementById('modal-box').showModal();
}
const dialog = document.querySelector("dialog");
dialog.addEventListener("click", function(event) {
  if (event.target === dialog) {
    dialog.close();
  }
});

function expandSubModal(text) {
    document.getElementById('modal-sublevel').innerHTML = text;
    document.getElementById('modal-sublevel').style.display = "block";
}
document.getElementById('modal-text').onclick = function(event) {
    if (event.target == document.getElementById('modal-text')) {
        document.getElementById('modal-sublevel').style.display = "none";
    }
}

function closeAllSelect(el) {
  let selects = document.querySelectorAll('.custom-selector.select-show, .custom-selector-multiple.select-show');
  let sz = selects.length;
  let toggle = true;
  if (el) {
    for (let i = 0; i<sz; i++) {
      const path = el.originalEvent.path || el.originalEvent.composedPath();
      if (path.includes(selects[i])) {
        toggle = false;
        break;
      }
    }
  }
  if (toggle) {
    selects.forEach((el) => {
      el.classList.remove('select-show');
    });
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
    if (evt) {
      evt.currentTarget.className += " active";
    }
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

function multiselectSubmit(source, link, param) {
  let select = $('#'+source);

  let options = select.children();
  let sz = options.length;

  let vals = [];

  for(let i=0; i<sz; i++) {
    if (!options[i].selected) continue;

    vals.push($(options[i]).attr('value'));
  }
  window.location = link + (link.indexOf('?') != -1 ? '&' : '?') + (vals.length ? param + '=' + vals.join(',') : '');
}

$.tablesorter.addParser({
  id: 'valuesort',
  is: function(s, table, cell, $cell) {
    return false;
  },
  format: function(s, table, cell, cellIndex) {
    return $(cell).attr('value');
  },
  type: 'numeric'
});