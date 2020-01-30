{if $is_logged_in}
<div>
    {$logged_as}
    <div class="logged_as" {$logout_href}>{$logout_label}</div>
</div>
{else}

<div class="logo">{$logo}</div>

<div id="login-screen" class="container">

                {if $banned}
                    <p class="alert alert-danger">{'You have exceeded the number of allowed login attempts.'|t}</p>
                    <p><a href="{$epesi_url}">{'Host banned. Click here to refresh.'|t}</a></p>
                {else}

                    {$form_data.javascript}

                    <form {$form_data.attributes}>
                        {$form_data.hidden}
                        <!-- Display the fields -->

                        {if $is_demo}
                            <p><strong>Epesi is in DEMO mode!</strong></p>
                        {/if}
                        {if isset($message)}
                            <div class="alert alert-info text-center">
                                {$message}
                            </div>
                            {if isset($message_action)}<p>{$message_action}</p>{/if}
                        {else}
                               
                                <div class="form-group">
                                <p class="error">{$form_data.username.error}</p>
                                    <label>{$form_data.username.label}</label>
                                    {$form_data.username.html}
                                </div>
                            
                            {if $mode=='recover_pass'}
                                    <div class="form-group">
                                     <p class="error">{$form_data.mail.error}</p>
                                    <label>{$form_data.mail.label}&nbsp;&nbsp;</label>
                                    {$form_data.mail.html}
                                    </div>
                                    {$form_data.submit_button.html}<a {$back_href}><p>{"Cancel"|t}</p></a>
                            
                            {else}
                                <div class="form-group">
                                    <label>{$form_data.password.label}</label>
                                    {$form_data.password.html}
                                </div>
                                {$form_data.submit_button.html}
                                <div class="checkbox">
                                    <label>{$form_data.autologin.html}</label>
                                    <small>{$form_data.warning.html}</small>
                                </div>
                            {/if}
                        {/if}
                    <p>{$form_data.recover_password.html}</p>
                    </form>
                {/if}
                
</div>

 <div class="footer">
    {if isset($donation_note)}
        <p>{$donation_note}</p>
    {/if}
	
    <div><a href="https://epe.si"><img src="images/epesi-powered.png"></a></div>
	<div>Copyright &copy; 2006-2020 by Janusz Tylek</div>
	<div class="support">Support: <a href="https://epesi.org">https://epesi.org</a></div>
</div>











{/if}







































































