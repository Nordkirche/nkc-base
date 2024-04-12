define(['jquery', 'TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/NkcBase/backend'], function ($, Modal) {

    var NkcBaseBackendApp = function () {
        this.suggestStringBuffer = '';
        this.suggestTimeout = null;
    };

    NkcBaseBackendApp.prototype.suggest = function (fieldId, searchString, allowedObjects) {

        var $searchField = $('#' + fieldId + '-search');
        var $suggestList = $('#' + fieldId + '-search-suggest');

        $.ajax({
            url: TYPO3.settings.ajaxUrls['NkcBase::suggest'],
            type: 'GET',
            data: 'search=' + searchString + '&allowed=' + allowedObjects,
            beforeSend: function (xhr) {
                $searchField.addClass('loading');
            },
            complete: function (xhr, json) {
                $searchField.removeClass('loading');
            },
            error: function (xhr, textStatus, errorThrown) {
                $searchField.removeClass('loading');
                self.messageError.show();
            },
            success: function (data) {

                $searchField.removeClass('loading');

                if (data.items.length) {
                    $suggestList.html('');
                    $suggestList.show();
                    $.each(data.items, function (index, item) {
                        var element = '<a href="#" data-label="' + item.name + '" data-uid="' + item.uri + '"><span class="suggest-label">' + item.name + '</span> <span class="suggest-uid">[' + item.id + ']</span><br></a>';
                        $suggestList.append(element);
                    });
                } else {
                    $suggestList.html('');
                    $suggestList.hide();
                }
            }
        });
    };

    NkcBaseBackendApp.prototype.init = function () {

        var context = this;

        // Suggest and fulltext filter
        $('body').on('keyup', '.napi-search', function () {

            var $searchField = $(this);
            var fieldId = $(this).data('field-id');
            var $suggestList = $('#' + fieldId + '-search-suggest');

            if (($searchField.val().length > 2) && ($searchField.val() !== context.suggestStringBuffer)) {

                context.suggestStringBuffer = $searchField.val();

                window.clearTimeout(context.suggestTimeout);

                context.suggestTimeout = window.setTimeout(function () {
                    context.suggest(fieldId, $searchField.val(), $searchField.data('allowed'));
                }, 250);

            } else {

                context.suggestStringBuffer = $searchField.val();
                $suggestList.html('');
                $suggestList.hide();
            }
        });

        $('body').on('mouseleave', '.autocomplete-results', function (e) {
            $(this).hide();
        });

        $('body').on('click', '.autocomplete-results a', function (e) {
            e.preventDefault();

            var $autocompleteResults = $(this).parent('.autocomplete-results');
            var fieldName = $autocompleteResults.data('fieldname');
            var fieldId = $autocompleteResults.data('field-id');
            var $valueField = $('#tceforms-multiselect-value-' + fieldId);

            // Add item to select field
            $('#' + fieldName).append('<option value="' + $(this).data('uid') + '">' + $(this).data('label') + '</option>');
            $('#' + fieldId + '-search-suggest').hide();
            $('#' + fieldId + '-search').val('');

            // Add item to hidden field
            var currentList = $valueField.val();
            currentList += (currentList.length ? ',' : '') + $(this).data('uid');
            $valueField.val(currentList);

            // Validate selection
            $formGroup = $('#' + fieldName).parent('.form-wizards-element').parent('.form-wizards-wrap').parent('.formengine-field-item').parent('.formengine-field-item').parent('.form-group');
            if (context.resolveValidationRules(fieldName) == true) {
                $formGroup.removeClass('has-error');
            } else {
                $formGroup.addClass('has-error');
            }

        });

      $('body').on('click', '.napi-wizard', function(e) {
        e.preventDefault();
        var configuration = {
          type: Modal.types.iframe,
          content: $(this).data('url'),
          size: Modal.sizes.large
        };
        Modal.advanced(configuration);

      });
    };


    NkcBaseBackendApp.prototype.resolveValidationRules = function (field) {

        var result = true;

        $field = $('#' + field);
        rules = $field.data('formengine-validation-rules');

        $.each(rules, function (k, rule) {
            if ((rule.minItems > 0) && (rule.minItems > $field.find('option').length)) {
                result = false;
            }
            if ((rule.maxItems > 0) && (rule.maxItems < $field.find('option').length)) {
                result = false;
            }
        });
        return result;
    };


    $(document).ready(function() {

        var NkcBaseBackend = new NkcBaseBackendApp;

        NkcBaseBackend.init();

    });
});
