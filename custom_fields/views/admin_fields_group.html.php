<?php defined("SYSPATH") or die("No direct script access.") ?>
<? if ($context == 'all'): ?>
<script type="text/javascript">
  $(function() {
    //$("#field-blocks ul").sortable({
    $("#available-fields-all").sortable({
      connectWith: ".g-sortable-blocks",
      opacity: .7,
      placeholder: "g-target",
      update: function(event,ui) {
        var field_blocks = "";
        var ulid = $(this).attr("id");
        var context = ulid.replace("available-fields-", "");
        $("#" + ulid + " li").each(function(i) {
          field_blocks += "&block["+i+"]="+$(this).attr("ref");
        });
        $.getJSON($("#field-blocks").attr("ref").replace("__SORTED__", field_blocks).replace("__CONTEXT__", context), function(data) {
          if (data.result == "success") {
            $("ul#available-fields-all").html(data.all);
            $("ul#available-fields-album").html(data.album);
            $("ul#available-fields-photo").html(data.photo);
            $("#g-action-status").remove();
            var message = "<ul id=\"g-action-status\" class=\"g-message-block\">";
            message += "<li class=\"g-success\">" + data.message + "</li>";
            message += "</ul>";
            $("#g-group-admin h2").after(message);
            $("#g-action-status li").gallery_show_message();
          }
        });
      }
    }).disableSelection();
  });

</script>
<? endif ?>

<? $v = new View("admin_context_block.html"); $v->group = $group; $v->context = $context; ?>
<?= $v ?>
