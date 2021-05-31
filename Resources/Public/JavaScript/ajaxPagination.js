$(document).ready(function() {
    
    $('body').on('click', '.ajax-list-wrapper .ajax-list__next-page', function(e) {

        var button = $(this);
        var spinner = button.find('.load-more');
        var requestUri = button.data('request-uri');

        if (!spinner.hasClass('load-more--loading')) {
            spinner.addClass('load-more--loading');
            if (requestUri.length) {
                $.get(requestUri, function(result) {
                    spinner.removeClass('load-more--loading');
                    if (result.length) {
                        var elements = $(result).find('.ajax-list > *').hide();
                        if (elements.length) {
                            var nextPage = $(result).find('.ajax-list__next-page');
                            if (nextPage.length) {
                                button.data('request-uri', nextPage.data('request-uri'));
                            } else {
                                button.fadeOut();
                            }
                            $('.ajax-list').append(elements);

                            if(window.nordkirche && window.nordkirche.lazyload) {
                                window.nordkirche.lazyload.update();
                            }

                            elements.fadeIn();
                        } else {
                            button.fadeOut();
                        }
                    }
                });
            }
        }

    });

});
