{* AdminLTE stepper for Utils_Wizard - see AI-shared/import-wizard-plan.md.

   Overrides the legacy bare <ul> in ../theme/default.tpl (still used verbatim
   by the plain/default theme) via Base_ThemeResolver's per-file theme_adminltedark
   fallback (modules/Base/Theme/resolver.php) - picked up automatically by every
   Utils_Wizard consumer (FirstRun, Develop/ModuleCreator, Premium/Import's
   guided import), no changes needed in any of them.

   $captions/$active_caption_key/$page are assigned by Wizard_0::body() exactly
   as for the legacy template. Grouping/status computed here in PHP rather than
   as Smarty boolean expressions, to keep the template itself close to the
   original's plain {if}/{foreach} style. *}
{php}
	$captions = $this->get_template_vars('captions');
	$active_caption_key = $this->get_template_vars('active_caption_key');

	/* Group into top-level (level 0) steps, with any deeper-level captions
	   nested under the preceding top-level one - this is only exercised by
	   Develop/ModuleCreator's dynamic per-table sub-steps today, so it's kept
	   to a simple indented sub-list rather than a fully general nested
	   stepper. $owner_of maps every caption key (level 0 or nested) to the
	   $steps index of its owning top-level step, so the active step - which
	   may itself be a nested one - resolves with a single lookup below. */
	$steps = array();
	$owner_of = array();
	$last_index = -1;
	foreach ($captions as $key => $cap) {
		if ($cap['level'] == 0) {
			$steps[] = array('caption' => $cap['caption'], 'children' => array());
			$last_index = count($steps) - 1;
		} elseif ($last_index >= 0) {
			$steps[$last_index]['children'][] = $cap['caption'];
		}
		if ($last_index >= 0) $owner_of[$key] = $last_index;
	}

	$active_index = isset($owner_of[$active_caption_key]) ? $owner_of[$active_caption_key] : null;

	foreach ($steps as $i => &$step) {
		if ($active_index === null) $step['status'] = 'upcoming';
		elseif ($i < $active_index) $step['status'] = 'done';
		elseif ($i == $active_index) $step['status'] = 'active';
		else $step['status'] = 'upcoming';
		$step['show_children'] = ($step['status'] == 'active' && !empty($step['children']));
	}
	unset($step);

	$this->assign('wizard_steps', $steps);
{/php}
{if !empty($wizard_steps)}
<ol class="epesi-wizard-steps">
{foreach from=$wizard_steps item=step name=wsteps}
	<li class="epesi-wizard-step epesi-wizard-step-{$step.status}">
		<span class="epesi-wizard-step-marker">{if $step.status == 'done'}<i class="bi bi-check-lg"></i>{else}{$smarty.foreach.wsteps.iteration}{/if}</span>
		<span class="epesi-wizard-step-label">{$step.caption}</span>
		{if $step.show_children}
		<ul class="epesi-wizard-substeps">
		{foreach from=$step.children item=child}
			<li>{$child}</li>
		{/foreach}
		</ul>
		{/if}
	</li>
{/foreach}
</ol>
{/if}
{$page}
