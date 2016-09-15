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
                    <div class="pull-left">{$menu}</div>
                    <button class="btn btn-default pull-right" {$home.href}>
                        <div id="home-bar1">
                                {$home.label}
                        </div>
                    </button>
                </div>
                <div class="panel-body">
                    {$logo}
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {if $quick_access_menu}
                        <div class="quick-access-bar">{$quick_access_menu}</div>
                    {/if}
                    <div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div>
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
                    <div class="pull-right">{$indicator}</div>
                </div>
                <div class="panel-body">
                        <div class="search" id="search_box" style="margin-bottom: 8px;">{$search}</div>
                        <div class="filter" id="filter_box">{$filter}</div>
                </div>
            </div>
        </div>
    </div>
    <!-- -->
    <div id="content">
        <div id="content_body" style="top: 50px;">
            {$main}
        </div>
    </div>

    <footer class="footer">
        <div class="container-fluid">
            <p class="text-muted">
                <a href="http://epe.si" target="_blank"><b>EPESI</b> powered</a>
                <span style="float: right">{$version_no}</span>
                {if isset($donate)}
                <span style="float: right; margin-right: 30px">{$donate}</span>
                {/if}
            </p>
        </div>
    </footer>

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

    <style>
        html {
            position: relative;
            min-height: 100%;
        }

        body {
            /* Margin bottom by footer height */
            margin-bottom: 60px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            /* Set the fixed height of the footer here */
            height: 60px;
            background-color: #f5f5f5;
            margin: 0 -15px;
        }

        .footer .container-fluid {
            width: auto;
            padding: 0 15px;
        }
        .footer .container-fluid .text-muted {
            margin: 20px 0;
        }
    </style>
{/literal}

{/if}
