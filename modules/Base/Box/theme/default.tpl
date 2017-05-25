{if !$logged}
    <div id="Base_Box__login">
        <div class="status">{$status}</div>
        <div class="entry">{$login}</div>
    </div>
{else}
    <div class="col-md-3 left_col">

        <div id="hidden-home-div" data-toggle="tooltip" data-placement="bottom" title="{$home.label|escape:html|escape:quotes}" {$home.href}>
            <div id="hidden-home-box">
                <a id="home-glyph-a" >
                    <span class="glyphicon glyphicon-home" aria-hidden="true"></span>
                </a>
            </div>
            <div id="hidden-home-gradient"></div>
        </div>

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
            <br/>
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

        <div class="nav_menu" style="padding-top: 0; margin-top: 0">
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
        <div id="left-gradient" style=""> </div>
        <!-- -->
        <div id="content">
            <div id="content_body" style="padding-top: 70px">
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
