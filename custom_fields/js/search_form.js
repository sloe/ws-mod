  // Initialize a short form. Short forms may contain only one text input.
  $.fn.gallery_short_form_mine = function() {
    return this.each(function(i){
      var label = $(this).find("label:first");
      var input = $(this).find("input[type=text]:first");
      var button = $(this).find("input[type=submit]");

      button.enable(true);
      input.unbind();

      // Set the input value equal to label text
      if (input.val() == "") {
        input.val(label.html());
        //button.enable(false);
      }

      // Attach event listeners to the input
      input.bind("focus", function(e) {
        // Empty input value if it equals it's label
        if ($(this).val() == label.html()) {
          $(this).val("");
        }
        //button.enable(true);
      });

      input.bind("blur", function(e){
        // Reset the input value if it's empty
        if ($(this).val() == "") {
          $(this).val(label.html());
          //button.enable(false);
        }
      });
      
      button.bind("click", function(e){
        // delete the search query if it's the default label
        if ($(input).val() == label.html()) {
          $(input).val("");
        }
      });
    });
  };

// Initialize short forms, my type
$(document).ready(function($) {
  $(".g-short-form").gallery_short_form_mine(); 
  
  // keep mont/year dropdown values on the same line
  $('select[name$="_year"]').parent().css('clear', 'none');

  // remove the corner from the button at the bottom
  // Place button's on the left for RTL languages
  /*
  if ($(".rtl").length) {
    $(".g-short-form input[type=submit] :last").removeClass("ui-corner-left");
  } else {
    $(".g-short-form input[type=submit] :last").removeClass("ui-corner-right");
  }
  */
});

// Submit search form after hacking values into it
function custom_fields_submit(paramName, paramValue, submit)
{
  if ( submit == null ) {
    submit = true;
  }
  newInputContent = '<input name="' + paramName + '" value="' + paramValue + '" type="hidden"/>';
  $("#g-custom-fields-form").append(newInputContent);
  if ( submit )
  {
    $("#g-custom-fields-form").submit();
  }
  return false;
}

// Submit search form after hacking values into it
function custom_fields_paging(page)
{
  newPageInput = '<input name="page" value="' + page + '" type="hidden"/>';
  $("#g-custom-fields-form-hidden").append(newPageInput);
  $("#g-custom-fields-form-hidden").submit();
  return false;
}

