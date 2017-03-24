{if !$logged}
    <div id="Base_Box__login">
        <div class="status">{$status}</div>
        <div class="entry">{$login}</div>
    </div>
{else}
<div class="col-md-3 left_col">
    <div class="left_col scroll-view">
        <div class="navbar nav_title" style="border: 0; padding: 20px 15px 15px 5px">
            {$logo}
        </div>

        <div class="clearfix"></div>

        <!-- menu profile quick info -->
        <div class="profile">
          <div class="profile_pic">
            <img src="images/img.jpg" alt="..." class="img-circle profile_img">
          </div>
          <div class="profile_info">
            <span>Welcome,</span>
            <h2>John Doe</h2>
          </div>
        </div>
        <!-- /menu profile quick info -->

        <br />

        <!-- sidebar menu -->
        <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
          {$menu}
        </div>
        <!-- /sidebar menu -->

        <!-- /menu footer buttons -->
        <div class="sidebar-footer hidden-small">
        <a data-toggle="tooltip" data-placement="top" title="{$home.label|escape:html|escape:quotes}" {$home.href}>
        <span class="glyphicon glyphicon-home" aria-hidden="true"></span>
        </a>
        <a data-toggle="tooltip" data-placement="top" title="Settings">
        <span class="glyphicon glyphicon-cog" aria-hidden="true"></span>
        </a>
<!--        <a data-toggle="tooltip" data-placement="top" title="FullScreen">
        <span class="glyphicon glyphicon-fullscreen" aria-hidden="true"></span>
      </a>-->
        <a data-toggle="tooltip" data-placement="top" title="Lock">
        <span class="glyphicon glyphicon-eye-close" aria-hidden="true"></span>
        </a>
        <a data-toggle="tooltip" data-placement="top" title="Logout">
        <span class="glyphicon glyphicon-off" aria-hidden="true"></span>
        </a>
        </div>
        <!-- /menu footer buttons -->
    </div>
</div>


<!-- top navigation -->
        <div class="top_nav">
          <div class="nav_menu">
            <nav>
              <div class="nav toggle">
                <a id="menu_toggle"><i class="fa fa-bars"></i></a>
              </div>

              <ul class="nav navbar-nav navbar-right">
                <li class="">
                  <a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                    <img src="images/img.jpg" alt="">John Doe
                    <span class=" fa fa-angle-down"></span>
                  </a>
                  <ul class="dropdown-menu dropdown-usermenu pull-right">
                    <li><a href="javascript:;"> Profile</a></li>
                    <li>
                      <a href="javascript:;">
                        <span class="badge bg-red pull-right">50%</span>
                        <span>Settings</span>
                      </a>
                    </li>
                    <li><a {$help}>{"Help"|t}</a></li>
                    <li><a href="login.html"><i class="fa fa-sign-out pull-right"></i> Log Out</a></li>
                  </ul>
                </li>

                <li role="presentation" class="dropdown">
                  <a href="javascript:;" class="dropdown-toggle info-number" data-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-envelope-o"></i>
                    <span class="badge bg-green">6</span>
                  </a>
                  <ul id="menu1" class="dropdown-menu list-unstyled msg_list" role="menu">
                    <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>
                    <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>
                    <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>
                    <li>
                      <a>
                        <span class="image"><img src="images/img.jpg" alt="Profile Image" /></span>
                        <span>
                          <span>John Smith</span>
                          <span class="time">3 mins ago</span>
                        </span>
                        <span class="message">
                          Film festivals used to be do-or-die moments for movie makers. They were where...
                        </span>
                      </a>
                    </li>
                    <li>
                      <div class="text-center">
                        <a>
                          <strong>See All Alerts</strong>
                          <i class="fa fa-angle-right"></i>
                        </a>
                      </div>
                    </li>
                  </ul>
                </li>
              </ul>

              {$actionbar}

            </nav>
          </div>
        </div>
<!-- /top navigation -->


<div class="right_col" role="main">
    <header class="row">

        <div class="col-lg-3 col-sm-6 col-xs-12 pull-right">
            <div class="panel panel-default">

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
