(function($) {
   // Based on the Password Strength Indictor By Benjamin Sterling
   // http://benjaminsterling.com/password-strength-indicator-and-generator/
   $.widget("ui.custom_field_type_change",  {
     _init: function() {
       var self = this;
       maxSelectionIdOnLoad = window.maxSelectionId;
       operation = window.operation != null ? window.operation : 'edit';
       typeOnLoad = $(this.element).val();
       formRange = $(this.element).closest("form").attr("id");
       selectionsAdded = false;
       // add initial 'more' button
       if ( typeOnLoad != "freetext" && typeOnLoad != "month" && typeOnLoad != "integer") {
         var form = $("#" + formRange + " fieldset ul");
         $(form).append('<li id="' + formRange + '_plus"><a href="#" onclick="return addMoreSelections(1)">(+)</a></li>');
       }
       $(this.element).change(function() {
        self.addHideSelections($(this).val());
       });
     },

     addHideSelections: function(value) {
      if ( self.operation == 'edit' ) {
        // do not let user go from multi to free input, gotta delete and readd field in this case!
        if ((value == "freetext" || value == "month" || value == "integer") && self.typeOnLoad != "freetext" && self.typeOnLoad != "month" && self.typeOnLoad != "integer") {
          alert('You can not change a selection based type into freetext, integer or month input. Delete and add a new field!');
          $(this.element).val(self.typeOnLoad);
          return;
        }

        // do not let user go anywhere from freetext, integer or month (&& check is against ugly browsers, foolproof)
        if ((self.typeOnLoad == "freetext" || self.typeOnLoad == "month" || self.typeOnLoad == "integer") && (value != self.typeOnLoad)) {
          alert('You can not change free text input, integer or month type into something else. Delete and add a new field!');
          $(this.element).val(self.typeOnLoad);
          return;
        }
      }
     
      // add selections if going from freetext to selection based
      if (value != "freetext" && value != "month" && value != "integer" && (self.typeOnLoad == "freetext" || self.typeOnLoad == "month")) {
        if ( !self.selectionsAdded ) {
          // add a few selections (3)
          var lastli = $("#" + self.formRange + " ul li:last");
          $(lastli).before('<li id="' + self.formRange + '_plus"><a href="#" onclick="return addMoreSelections(1)">(+)</a></li>');

          // add 3 inputs for start
          addMoreSelections(3);

          // note that selections are added now (TODO: can just rely on maxSelectionId without this var)
          self.selectionsAdded = true;
        } else {
          $('input[name^="selection"], #' + self.formRange + '_plus a').parent().show("slow");
        }
      } else {
        if ( value == "freetext" ) {
          $('input[name="max_length"]').parent().show("slow");
        }
      }

      if ( value == "freetext" || value == "month" || value == "integer" ) {
        // TODO: simply hide selections if any
        $('input[name^="selection"], #' + self.formRange + '_plus a').parent().hide("slow");
      }

      if (value != "freetext") {
        $('input[name="max_length"]').parent().hide("slow");
      }
     }
   });
 })(jQuery);

function addMoreSelections(count) {
  i = 1;
  var ul = $("#" + self.formRange + "_plus:parent");
  base = window.maxSelectionId;
  while( i <= count ) {
    $(ul).before('<li><label for="selections[' + (base+i) + ']">Option #' + (base+i+1) + '</label><input type="text" class="textbox" value="" name="selections[' + (base+i) + ']"></li>');
    i++;
    window.maxSelectionId++;
  }
  return false;
}

// TODO: could be in an admin only js
function setSortableSelections() {
    $("#g-edit-field-form input.g-draggable-child").parent().addClass("g-draggable");
    $("#g-edit-field-form ul").addClass("g-field-list g-sortable-blocks ui-sortable");
    $("#g-edit-field-form ul").sortable({
      connectWith: ".g-sortable-blocks",
      opacity: .7,
      placeholder: "g-target",
      update: function(event,ui) {
        var field_blocks = "";
        $("#g-edit-field-form ul li.g-draggable").each(function(i) {
          field_blocks += "&block["+i+"]="+$(this).children("input").attr("ref");
        });
        $.getJSON(orderingUrl.replace("__SORTED__", field_blocks), function(data) {
          if (data.result == "success") {
            //$("#g-edit-field-form ul").html(data.all);
            $("#g-action-status").remove();
            var message = "<ul id=\"g-action-status\" class=\"g-message-block\">";
            message += "<li class=\"g-success\">" + data.message + "</li>";
            message += "</ul>";
            $("#g-user-admin h2").after(message);
            $("#g-action-status li").gallery_show_message();
          }
        });
      }
    }).disableSelection();
 };
