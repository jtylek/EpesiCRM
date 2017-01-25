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
    <header class="row">
        <div class="col-lg-2 col-xs-6">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <div class="pull-left">{$menu}</div>
                    <button class="btn btn-default pull-right" {$home.href}>
                        <div id="home-bar1">
                                {$home.label}
                        </div>
                    </button>
                </div>
                <div class="panel-body logo-container">
                    {$logo}
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6 pull-right">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                  <div class="pull-left"><a {$help} class="btn btn-info">?</a></div>
                    <div class="pull-right">{$indicator}</div>
                </div>
                <div class="panel-body">
                        <div class="search" id="search_box" style="margin-bottom: 8px;">{$search}</div>
                        <div class="filter" id="filter_box">{$filter}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-7 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading vertical-align-middle">
                    <div id="module-indicator" class="pull-left">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div>
                    <div class="pull-right">
                      {if isset($donate)}
                        {$donate}
                      {/if}
                    </div>
                </div>
                <div class="panel-body">
                  {$actionbar}
                </div>
            </div>
        </div>
    </header>
    <!-- -->
    <div id="content">
        <div id="content_body" style="top: 50px;">
            {$main}
        </div>
    </div>

    <footer>
        <div class="pull-left">
            <a href="http://epe.si" target="_blank"><b>EPESI</b> powered</a>
        </div>
        <div class="pull-right">
            <span style="float: right">{$version_no}</span>
            {if isset($donate)}
                <span style="float: right; margin-right: 30px">{$donate}</span>
            {/if}
        </div>
    </footer>

    {$status}

{/if}
