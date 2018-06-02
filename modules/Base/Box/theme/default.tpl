{if !$logged}
    <div id="Base_Box__login">
        <div class="status">{$status}</div>
        <div class="entry">{$login}</div>
    </div>
{else}

    <header class="row">
        <div class="col-lg-2 col-xs-12 col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <div class="pull-left">{$menu}</div>
                    <button class="btn btn-default pull-right" style="overflow: hidden;text-overflow: ellipsis;" {$home.href}>
                        <span class="glyphicon glyphicon-home" aria-hidden="true"></span>
                        <span class="hidden-xs">
                                {$home.label}
                        </span>
                    </button>
                </div>
                <div class="panel-body logo-container">
                    {$logo}
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 col-xs-12 pull-right">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                  <div class="pull-left"><a {$help} class="btn btn-info">?</a></div>
                    {$watchdog}
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
                    <div class="pull-right hidden-xs">
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

{php}
    load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
    eval_js_once('document.body.id=null'); //pointer-events:none;
{/php}
