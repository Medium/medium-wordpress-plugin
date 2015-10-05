jQuery(document).ready(function($) {
  // Handle Medium Status
  $postMediumStatusSelect = $("#post-medium-status-select");

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
    $crossLinkDiv = $(".misc-pub-medium-cross-link");
    if (status == "none") {
      $crossLinkDiv.addClass("hidden");
    } else {
      $crossLinkDiv.removeClass("hidden");
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

  // Handle Medium License
  $postMediumLicenseSelect = $("#post-medium-license-select");

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


  // Handle Medium Cross-link
  $postMediumCrossLinkSelect = $("#post-medium-cross-link-select");

  $("#medium-cross-link .edit-medium-cross-link").click(function () {
    if ($postMediumCrossLinkSelect.is(":hidden")) {
      $("#medium-cross-link-hidden").val($postMediumCrossLinkSelect.find("input:radio:checked").val())
      $postMediumCrossLinkSelect.slideDown("fast", function() {
        $postMediumCrossLinkSelect.find('input[type="radio"]')
            .first().focus();
      });
      $(this).hide();
    }
    return false;
  });

  $postMediumCrossLinkSelect.find(".save-post-medium-cross-link").click(function(event) {
    $postMediumCrossLinkSelect.slideUp("fast");
    $("#medium-cross-link .edit-medium-cross-link").show().focus();
    $("#post-medium-cross-link-display").html($postMediumCrossLinkSelect.find("input:radio:checked + label").html());
    event.preventDefault();
  });

  $postMediumCrossLinkSelect.find(".cancel-post-medium-cross-link").click(function(event) {
    $postMediumCrossLinkSelect.slideUp("fast", function () {
      $("#medium-cross-link-radio-" + $("#medium-cross-link-hidden").val()).prop("checked", true);
    });
    $("#medium-cross-link .edit-medium-cross-link").show().focus();
    event.preventDefault();
  });
});
