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
                <div class="nav-item d-none d-md-flex"><div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div></div>
                <div class="nav-item d-none d-md-flex">{$actionbar}</div>
                <div class="d-flex order-lg-2 ml-auto">
                    <div class="nav-item d-none d-md-flex">
                        {if isset($donate)}
                            {$donate}
                        {/if}
                    </div>
                    <div class="nav-item d-none d-md-flex">
                        <a {$launchpad_href} title="{'Launchpad'|t}" class="nav-link icon">
                            <i class="fa fa-th"></i>
                        </a>
                    </div>
                    {$watchdog}
                    {$quickaccess}
                    {$filter}
                    {$indicator}
                </div>

            </div>
        </div>
    </header>
    <header class="header collapse d-lg-flex p-0" id="headerMenuCollapse">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-2 ml-auto">
                    {$search}
                </div>
                <div class="col-lg order-lg-first">
                    {$menu}
                </div>
            </div>
        </div>
    </header>

    <!-- -->
    <div id="content" class="my-3 my-md-5">
        <div id="content_body" class="container-fluid">
            {$main}
        </div>
    </div>

    <footer class="footer">
        <div class="container-fluid">
            <div class="row align-items-center flex-row-reverse">
                <div class="col-auto ml-lg-auto">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item"><a href="https://forum.epesibim.com/">Forum</a></li>
                                <li class="list-inline-item"><a href="http://epe.si/support/">Support</a></li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            {if isset($donate)}
                                <span style="float: right; margin-right: 30px">{$donate}</span>
                            {/if}
                        </div>
                        <div class="col-auto">
                            <a href="https://github.com/Telaxus/EPESI" class="btn btn-outline-primary btn-sm">Source code</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-auto mt-3 mt-lg-0 text-center">
                    <a href="http://epe.si" target="_blank"><b>EPESI</b> powered</a> {$version_no}
                </div>
            </div>
        </div>
    </footer>

    {$status}

{/if}

{php}
    load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
    eval_js_once('document.body.id=null'); //pointer-events:none;
{/php}
