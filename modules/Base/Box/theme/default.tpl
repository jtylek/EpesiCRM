{if !$logged}
    <div id="Base_Box__login">
        <div class="status">{$status}</div>
        <div class="entry">{$login}</div>
    </div>
{else}
    <div class="col-md-3 left_col">

        <div id="login-div" class="navbar nav_title" style="border: 0">
            <a id="login-box" style="width: 75%; max-height: 57px">
                {$login}
            </a>
            <a id="home-glyph-a" data-toggle="tooltip" data-placement="bottom" title="{$home.label|escape:html|escape:quotes}" {$home.href}>
                <span class="glyphicon glyphicon-home" aria-hidden="true"></span>
            </a>
        </div>

        <div class="search-bar">
            {$search}
        </div>

        <div class="left_col scroll-view" id="leftside-menu">
            <div class="watchdog dropdown">
                <div class="row">
                    <div class="login">
                        {*{$login}*}
                        {*{$watchdog}*}
                    </div>
                </div>
            </div>

            <!-- menu profile quick info -->
            {*<div class="module_indicator">*}
            {*<h2>{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</h2>*}
            {*</div>*}
            <!-- /menu profile quick info -->

            <br />

            <!-- sidebar menu -->
            <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
                {$menu}
            </div>
        </div>
        <div class="sidebar-footer hidden-small" data-toggle="tooltip" data-placement="top" title="Soft-refresh">
            {$logo}
        </div>
    </div>


    <!-- top navigation -->
    <div class="top_nav navbar-fixed-top">

        <div class="nav_menu">
            <nav class="top-navigation">
                <div class="nav toggle">
                    <a id="menu_toggle"><i class="fa fa-bars"></i></a>
                </div>

                <ul class="nav navbar-nav navbar-right">

                </ul>

                {$actionbar}

            </nav>
        </div>
    </div>
    <!-- /top navigation -->


    <div class="right_col" role="main">

        <!-- -->
        <div id="content">
            <div id="content_body" style="padding-top: 110px">
                {$main}
            </div>
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
        <div class="clearfix"></div>
    </footer>

    {$status}

{/if}

{php}
    load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
    eval_js_once('document.body.id=null'); //pointer-events:none;
{/php}
