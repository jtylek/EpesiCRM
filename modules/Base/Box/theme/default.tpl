{if !$logged}
    <div id="Base_Box__login">
        <div class="status">{$status}</div>
        <div class="entry">{$login}</div>
    </div>
{else}

    {php}
        load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
        eval_js_once('document.body.id=null'); //pointer-events:none;
    {/php}
    <canvas class="Base_Help__tools" style="height:3000px;width:3000px;" id="help_canvas" width="3000px"
            height="3000px"></canvas>
    <img class="Base_Help__tools" style="display: none;" id="Base_Help__help_arrow"
         src="{$theme_dir}/Base/Help/arrow.png"/>
    <div class="Base_Help__tools comment" style="display: none;" id="Base_Help__help_comment">
        <div id="Base_Help__help_comment_contents"></div>
        <div class="button_next" id="Base_Help__button_next">{'Next'|t}</div>
        <div class="button_next" id="Base_Help__button_finish">{'Finish'|t}</div>
    </div>
    <div class="row">
        <div class="col-md-2">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <button class="btn btn-default pull-left">{$menu}</button>
                    <button class="btn btn-default pull-right" {$home.href}>
                        <div id="home-bar1">
                                {$home.label}
                        </div>
                    </button>
                </div>
                <div class="panel-body">
                    <div class="shadow_css3_logo_border">{$logo}</div>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {if $quick_access_menu}
                        <div class="quick-access-bar">{$quick_access_menu}</div>
                    {/if}
                    <span class="powered" nowrap="1">
                        <span>
                            <a href="http://epe.si" target="_blank"><b>EPESI</b> powered</a>&nbsp;
                        </span>
                        <span>{$version_no}</span>
                    </span>
                    {if isset($donate)}
                        <span class="donate" nowrap="1">{$donate}</span>
                    {/if}
                </div>
                <div class="panel-body">
                    {$actionbar}
                </div>
            </div>
        </div>
        <div class="col-md-1">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {$help}
                </div>
                <div class="panel-body" id="launchpad_button_section">
                    {$launchpad}
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div>
                </div>
                <div class="panel-body">
                        <div class="login clearfix">{$login}</div>
                        <div class="search" id="search_box">{$search}</div>
                        <div class="filter" id="filter_box">{$filter}</div>
                </div>
            </div>
        </div>
    </div>
    <!-- -->
    <div id="content">
        <div id="content_body" style="top: 50px;">
            <center>{$main}</center>
        </div>
    </div>
    {$status}

{literal}
    <style type="text/css">
        div > div#top_bar {
            position: fixed;
        }

        div > div#bottom_bar {
            position: fixed;
        }
    </style>
{/literal}

{/if}
