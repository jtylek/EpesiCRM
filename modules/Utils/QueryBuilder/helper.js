/**
 * Created by ajb on 16.06.15.
 */

function Utils_QueryBuilder(form_name, form_element_id, builder_id, options_json, rules_json, error_msg) {
    var form_selector = 'form[name="' + form_name +'"]';
    var form_element_selector = '#' + form_element_id;
    var builder_selector = '#' + builder_id;
    var events_str = "afterAddGroup.queryBuilder afterDeleteGroup.queryBuilder afterAddRule.queryBuilder afterDeleteRule.queryBuilder afterUpdateRuleValue.queryBuilder afterUpdateRuleFilter.queryBuilder afterUpdateRuleOperator.queryBuilder afterUpdateGroupCondition.queryBuilder afterReset.queryBuilder";
    if (jq(builder_selector).length == 0) return;
    jq(builder_selector).queryBuilder(options_json).on(events_str, function (event, rule, error, value) {
        jq(form_element_selector).val(JSON.stringify(jq(builder_selector).queryBuilder("getRules")));
    });
    jq(builder_selector).queryBuilder('setRules', rules_json);
    jq(form_selector).attr('onsubmit', 'if (!jq(\'' + builder_selector + '\').queryBuilder(\'validate\')) { alert(\'' + error_msg + '\'); return false; } else { ' + jq(form_selector).attr('onsubmit') + ' };');
}
