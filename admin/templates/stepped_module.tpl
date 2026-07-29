<h4 class="mb-3">{$header}</h4>
<div class="mb-3">{$content}</div>
{if $show_button}
<div class="text-center mt-3">
	<form method="post" name="action_button">
		<input type="hidden" name="{$step_var}" value="{$next_step}" />
		{if $auto_run}
	</form>
	<script type="text/javascript">document.action_button.submit()</script>
		{else}
		<button type="submit" class="btn btn-success">{$button_text|t}</button>
	</form>
		{/if}
</div>
{/if}
