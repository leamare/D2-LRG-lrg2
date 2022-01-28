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
      if (isOpen) {
        $(this).siblings('input.search-filter-selector').focus();
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

      $(div).append(option);
    }

    $(el).append( div );

    if (sz > 10) {
      const placeholder = $(select).attr('data-placeholder');
      const input = $(`<input type="text" name="filter" class="search-filter-selector" placeholder="${placeholder}" />`);

      $(input).on('keyup', function() {
        const value = $(this).val().toLowerCase();
        const options = $(this).parent().find('.custom-selector-list')[0];
        
        $(options).children('.custom-selector-option').filter(function() {
          const aliases = $(this).attr('data-aliases');
          const line = $(this).text().toLowerCase() + (aliases ? ' ' + aliases.toLowerCase() : '');
          $(this).toggle( line.indexOf(value) !== -1 )
        });
      });

      $(input).on('click', function(e) {
        e.stopPropagation();
      });

      $(input).insertBefore( div );

      $(el).addClass('searchable');
    }
  }

  $(document).on('click', closeAllSelect);
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