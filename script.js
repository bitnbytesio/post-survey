jQuery(function ($) {

    $(".post-survey-button").click(function () {

        $(".post-survey-alert").remove();

        var post_survey = {
            post_id: $(this).data('id'),
            type: $(this).data('type'),
            comment: $("#post-survey-comment").val() || null
        };

        jQuery.ajax({
                url: _wp_post_survey.ajax_url,
                data: {action: 'post_survey', 'data': post_survey},
                dataType: "json",
                type: "post",
                success: function (o) {
                    if (o.success == true) {

                        $("#post-survey-comment").val('');

                        if (typeof o.redirect !== 'undefined') {
                            window.location = o.redirect;
                            return;
                        }


                        if (typeof o.alert !== 'undefined') {

                            postSurveyAlert(o.alert);

                        } else {

                            postSurveyAlert("Thanks for your valuable feedback!");

                        }

                        if (o.count_positive && o.count_negative) {
                            $('.post-survey-button[data-type="positive"] .count').text(o.count_positive);
                        }
                        $('.post-survey-button[data-type="negative"] .count').text(o.count_negative);


                    } else if (o.success == false && typeof o.alert !== 'undefined') {

                        postSurveyAlert(o.alert);

                    } else {

                        postSurveyAlert("We are sorry, Service is unavialable!");

                    }
                }
            }
        );


    });

});

function postSurveyAlert(msg) {
    var html = '<p class="alert post-survey-alert">' + msg + '</p>';
    jQuery("#post-survey-container .post-survey-header").append(html);
    setTimeout(function () {
        jQuery(".post-survey-alert").remove();
    }, 6000);
}