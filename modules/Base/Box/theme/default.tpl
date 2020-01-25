{if !$logged}

	<!---- <div class="status">{$status}</div> -->
<div class="login_form id="login_form>{$login}</div>

{else}

	
		<div class="logo" {$home.href}></div>

		<div class="top_bar">epesi {$version_no}</div>

		<div class="top_bar" id="top_bar">{$help}</div>	
				
		<div class="home-label">{$home.label}</div>

		{if $quick_access_menu}
				<div class="quick-access-bar">{$quick_access_menu}</div>
		{/if}
					
		{if $moduleindicator}
				<div class="module-indicator">{$moduleindicator}</div>
		{/if}
		
		{if isset($donate)}
				<div class="donate">{$donate}</td>
		{/if}
		
		<div class="logo">{$logo}</div> 
		
		<div class="menu" id="Menu">{$menu}</div>

		<div id="ActionBar" class="ActionBar">
				<div class="icons">{$actionbar}</div>
		</div>
					
		<div class="filter" id="filter_box">{$filter}</div>
					
		<div class="icons_launchpad" id="launchpad_button_section" style="display:none;">{$launchpad}</div>
					
		<div class="search" id="search_box">{$search}</div>

		<div id="content_body">{$main}</div>

		<div id="ActionBar" class="ActionBar">
			<div class="icons">{$actionbar}</div>
		</div>

	</div>

	{literal}

	{/literal}

{/if}
