require(['jquery'], function($) {

  function resolveValidationRules(opener, field) {

    var result = true;

    field = opener.find(field);
    rules = field.data('formengine-validation-rules');

    $.each(rules, function(k, rule) {
      if ((rule.minItems > 0) && (rule.minItems > field.find('option').length)) {
        result = false;
      }
      if ((rule.maxItems > 0) && (rule.maxItems < field.find('option').length)) {
        result = false;
      }
    });
    return result;
  };

  function addListener() {
    $('body').on('click', '#doSearch', function(e) {
      e.preventDefault();
      $('#search').addClass('loading');
      $(this).attr('disabled', true);
      $('form').submit();
    });

    $('a.t3js-pageLink').on('click', function(e) {
      e.preventDefault();
      let opener = $('#typo3-contentIframe', parent.document).contents();
      let field = $('form').data('field');
      let element = '<option value="' + $(this).attr('href') + '">' + $(this).text() + '</option>';
      if (opener) {
        var valueField = opener.find('#tceforms-multiselect-value-' + field);
        var newValue = valueField.val();

        opener.find('#tceforms-multiselect-' + field).append(element);

        if (newValue != '')
          newValue += ',';

        newValue += $(this).attr('href');
        valueField.val(newValue);

        formGroup = opener.find('#tceforms-multiselect-' + field).parent('.form-wizards-element').parent('.form-wizards-wrap').parent('.formengine-field-item').parent('.formengine-field-item').parent('.form-group');

        if (resolveValidationRules(opener, '#tceforms-multiselect-' + field) == true) {
          formGroup.removeClass('has-error');
        } else {
          formGroup.addClass('has-error');
        }
      }
    });
  }

  $(document).ready(function() {
    addListener();
  });
});
