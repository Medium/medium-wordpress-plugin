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
    $crossLinkDiv = $(".misc-pub-medium-cross-link");
    $followerNotificationDiv = $(".misc-pub-medium-follower-notification");
    $publicationIdDiv = $(".misc-pub-medium-publication-id");
    if (status == "none") {
      $licenseDiv.addClass("hidden");
      $crossLinkDiv.addClass("hidden");
      $followerNotificationDiv.addClass("hidden");
      $publicationIdDiv.addClass("hidden");
    } else {
      $licenseDiv.removeClass("hidden");
      $crossLinkDiv.removeClass("hidden");
      $followerNotificationDiv.removeClass("hidden");
      $publicationIdDiv.removeClass("hidden");
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


  // Handle Medium follower notification
  $postMediumFollowerNotificationSelect = $("#post-medium-follower-notification-select");

  $("#medium-follower-notification .edit-medium-follower-notification").click(function () {
    if ($postMediumFollowerNotificationSelect.is(":hidden")) {
      $("#medium-follower-notification-hidden").val($postMediumFollowerNotificationSelect.find("input:radio:checked").val())
      $postMediumFollowerNotificationSelect.slideDown("fast", function() {
        $postMediumFollowerNotificationSelect.find('input[type="radio"]')
            .first().focus();
      });
      $(this).hide();
    }
    return false;
  });

  $postMediumFollowerNotificationSelect.find(".save-post-medium-follower-notification").click(function(event) {
    $postMediumFollowerNotificationSelect.slideUp("fast");
    $("#medium-follower-notification .edit-medium-follower-notification").show().focus();
    $("#post-medium-follower-notification-display").html($postMediumFollowerNotificationSelect.find("input:radio:checked + label").html());
    event.preventDefault();
  });

  $postMediumFollowerNotificationSelect.find(".cancel-post-medium-follower-notification").click(function(event) {
    $postMediumFollowerNotificationSelect.slideUp("fast", function () {
      $("#medium-follower-notification-radio-" + $("#medium-follower-notification-hidden").val()).prop("checked", true);
    });
    $("#medium-follower-notification .edit-medium-follower-notification").show().focus();
    event.preventDefault();
  });


  // Handle publication selection
  $postMediumPublicationIdSelect = $("#post-medium-publication-id-select");

  $("#medium-publication-id .edit-medium-publication-id").click(function () {
    if ($postMediumPublicationIdSelect.is(":hidden")) {
      $("#medium-publication-id-hidden").val($postMediumPublicationIdSelect.find("input:radio:checked").val())
      $postMediumPublicationIdSelect.slideDown("fast", function() {
        $postMediumPublicationIdSelect.find('input[type="radio"]')
            .first().focus();
      });
      $(this).hide();
    }
    return false;
  });

  $postMediumPublicationIdSelect.find(".save-post-medium-publication-id").click(function(event) {
    $postMediumPublicationIdSelect.slideUp("fast");
    $("#medium-publication-id .edit-medium-publication-id").show().focus();
    $("#post-medium-publication-id-display").html($postMediumPublicationIdSelect.find("input:radio:checked + label").html());
    var publishable = $postMediumPublicationIdSelect.find('input:radio:checked').data("publishable");
    $publicStatusRadio = $postMediumStatusSelect.find('input:radio[value="public"]');
    $unlistedStatusRadio = $postMediumStatusSelect.find('input:radio[value="unlisted"]');
    if (publishable) {
      $publicStatusRadio.removeAttr("disabled");
      $unlistedStatusRadio.removeAttr("disabled");
    } else {
      $publicStatusRadio.attr("disabled", "disabled");
      $unlistedStatusRadio.attr("disabled", "disabled");
      if ($publicStatusRadio.prop("checked") || $unlistedStatusRadio.prop("checked")) {
        $postMediumStatusSelect.find('input:radio[value="draft"]').prop("checked", true);
        $("#post-medium-status-display").html($postMediumStatusSelect.find("input:radio:checked + label").html());
      }
    }
    event.preventDefault();
  });

  $postMediumPublicationIdSelect.find(".cancel-post-medium-publication-id").click(function(event) {
    $postMediumPublicationIdSelect.slideUp("fast", function () {
      $("#medium-publication-id-radio-" + $("#medium-publication-id-hidden").val()).prop("checked", true);
    });
    $("#medium-publication-id .edit-medium-publication-id").show().focus();
    event.preventDefault();
  });


  // Handle refreshing of publications.
  $refreshPublicationsButton = $("#medium-refresh-publications");
  $mediumPublicationsDescription = $("#medium-publications-description");
  $mediumDefaultPublicationIdSelect = $("#medium_default_publication_id");

  $refreshPublicationsButton.click(function(event) {
    $refreshPublicationsButton.attr("disabled", "disabled");
    $mediumDefaultPublicationIdSelect.attr("disabled", "disabled");

    var data = {
      "action": "medium_refresh_publications"
    };

    $.post(ajaxurl, data, function(response) {
      $refreshPublicationsButton.removeAttr("disabled");
      $mediumDefaultPublicationIdSelect.removeAttr("disabled");
      var currentPublicationId = $mediumDefaultPublicationIdSelect.val();

      var result = JSON.parse(response)
      if (result.error) {
        var error
        switch (result.error.code) {
          case 6002:
            error = medium.errorMissingScope
            break
          default:
            error = medium.errorUnknown.replace("%s", result.error.code)
            break
        }
        alert(error);
      } else {
        $mediumDefaultPublicationIdSelect.find("option").each(function() {
          $(this).remove();
        });
        for (var publicationId in result) {
          var publication = result[publicationId]
          $("<option/>").attr("value", publicationId).attr("data-publishable", publication.publishable).text(publication.name)
            .appendTo($mediumDefaultPublicationIdSelect);
        }
        // Restore the selected option if possible.
        if (!$mediumDefaultPublicationIdSelect.find('option[value="' + currentPublicationId + '"]').length) {
          currentPublicationId = "";
        }
        $mediumDefaultPublicationIdSelect.val(currentPublicationId);
      }
    });
    event.preventDefault();
  })

});
