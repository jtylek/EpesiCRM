{if !$logged}
    <div id="Base_Box__login">
        <div class="status">{$status}</div>
        <div class="entry">{$login}</div>
    </div>
{else}

    <header class="header py-4">
        <div class="container-fluid">
            <div class="d-flex">
                    {$logo}
                <div class="nav-item d-none d-md-flex"><a class="btn btn-sm btn-secondary" title="{$home.label|escape:html|escape:quotes}" {$home.href}><i class="fa fa-home"></i> {$home.label}</a></div>
                <div class="d-flex order-lg-2 ml-auto">
                    <div class="nav-item d-none d-md-flex">
                        {if isset($donate)}
                            {$donate}
                        {/if}
                    </div>
                    {$watchdog}
                    {$indicator}
                </div>

            </div>
        </div>
    </header>
    <header class="header collapse d-lg-flex p-0" id="headerMenuCollapse">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg order-lg-first">
                    {$menu}
                </div>
            </div>
        </div>
    </header>



    <header class="row">
        <div class="col-lg-2 col-xs-12 col-sm-6">
        </div>
        <div class="col-lg-3 col-sm-6 col-xs-12 pull-right">
            <div class="card ">
                <div class="card-header clearfix">
                    <a data-toggle="tooltip" data-placement="bottom" title="Settings" {$settings_href}>
                        <span class="glyphicon glyphicon-cog" aria-hidden="true"></span>
                    </a>
                </div>
                <div class="card-body">
                        <div class="search" id="search_box" style="margin-bottom: 8px;">{$search}</div>
                        <div class="filter" id="filter_box">{$filter}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-7 col-xs-12">
            <div class="card ">
                <div class="card-header vertical-align-middle">
                    <div id="module-indicator" class="pull-left">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div>
                </div>
                <div class="card-body">
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
