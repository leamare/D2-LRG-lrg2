$(document).ready(function() {
  $('.list tbody tr').on('click', function() {
    $(this).toggleClass('highlighted');
  });

  $('table.sortable').tablesorter({
    sortInitialOrder: 'desc',
    stringTo: 'min',
    sortReset: true,
  });
  
  $(".search-filter").on("input", function() {
    const value = $(this).val().toLowerCase();
    const table = $(this).attr('data-table-filter-id');
    
    $("#" + table + " tbody tr").filter(function() {
      const aliasesRaw = $(this).find('img[data-aliases], a[data-aliases]');
      let aliases = $(this).attr('data-aliases');
      if (aliasesRaw.length) {
        for (let i=0; i<aliasesRaw.length; i++) {
          aliases += ' ' + $(aliasesRaw[i]).attr('data-aliases');
        }
      }
      const line = $(this).text().toLowerCase() + (aliases ? ' ' + aliases.toLowerCase() : '');
      $(this).toggle( line.indexOf(value) !== -1 )
    });
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
        const value = $(this).val().toLowerCase();
        const options = $(this).parent().find('.custom-selector-list')[0];
        
        $(options).children('.custom-selector-option').filter(function() {
          const aliases = $(this).attr('data-aliases');
          const line = $(this).text().toLowerCase() + (aliases ? ' ' + aliases.toLowerCase() : '');
          $(this).toggle( line.indexOf(value) !== -1 )
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
      
      $("#" + table + " tbody tr").filter(function() {
        $(this).toggle( status ? +$(this).attr(param) >= value : true );
      });
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
        $("#" + table + " tbody tr").filter(function() {
          let value = true;

          for (let filter in tables[table]) {
            const filterEl = tables[table][filter];
            value = value && (filterEl.status ? +$(this).attr(filterEl.param) >= filterEl.value : true)
          }

          $(this).toggle(value);
        });
      }
    }
  });

  $(document).on('click', closeAllSelect);
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

function closeAllSelect(el) {
  let selects = $('.custom-selector.select-show');
  let sz = selects.length;
  for (let i = 0; i<sz; i++) {
    if (el == selects[i]) continue;
    $(selects[i]).removeClass('select-show');
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