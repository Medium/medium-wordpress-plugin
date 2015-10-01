jQuery(document).ready(function($) {
  $postMediumStatusSelect = $("#post-medium-status-select");

  // Handle Medium Status
  $("#medium-status .edit-medium-status").click(function () {
    if ($postMediumStatusSelect.is(":hidden")) {
      $("#medium-status-hidden").val($postMediumStatusSelect.find("input:radio:checked").val())
      $postMediumStatusSelect.slideDown("fast", function() {
        $postMediumStatusSelect.find('input[type="radio"]')
            .first().focus();
      });
      $(this).hide();
    }
    return false;
  });

  $postMediumStatusSelect.find(".save-post-medium-status").click(function(event) {
    $postMediumStatusSelect.slideUp("fast");
    $("#medium-status .edit-medium-status").show().focus();
    $("#post-medium-status-display").html($postMediumStatusSelect.find("input:radio:checked + label").html());
    var status = $postMediumStatusSelect.find("input:radio:checked").val();
    $licenseDiv = $(".misc-pub-medium-license");
    if (status == "none") {
      $licenseDiv.addClass("hidden");
    } else {
      $licenseDiv.removeClass("hidden");
    }
    event.preventDefault();
  });

  $postMediumStatusSelect.find(".cancel-post-medium-status").click(function(event) {
    $postMediumStatusSelect.slideUp("fast", function () {
      $("#medium-status-radio-" + $("#medium-status-hidden").val()).prop("checked", true);
    });
    $("#medium-status .edit-medium-status").show().focus();
    event.preventDefault();
  });

  $postMediumLicenseSelect = $("#post-medium-license-select");

  // Handle Medium License
  $("#medium-license .edit-medium-license").click(function () {
    if ($postMediumLicenseSelect.is(":hidden")) {
      $("#medium-license-hidden").val($postMediumLicenseSelect.find("input:radio:checked").val())
      $postMediumLicenseSelect.slideDown("fast", function() {
        $postMediumLicenseSelect.find('input[type="radio"]')
            .first().focus();
      });
      $(this).hide();
    }
    return false;
  });

  $postMediumLicenseSelect.find(".save-post-medium-license").click(function(event) {
    $postMediumLicenseSelect.slideUp("fast");
    $("#medium-license .edit-medium-license").show().focus();
    $("#post-medium-license-display").html($postMediumLicenseSelect.find("input:radio:checked + label").html());
    event.preventDefault();
  });

  $postMediumLicenseSelect.find(".cancel-post-medium-license").click(function(event) {
    $postMediumLicenseSelect.slideUp("fast", function () {
      $("#medium-license-radio-" + $("#medium-license-hidden").val()).prop("checked", true);
    });
    $("#medium-license .edit-medium-license").show().focus();
    event.preventDefault();
  });
});
