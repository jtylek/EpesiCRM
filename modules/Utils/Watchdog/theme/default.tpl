<a href="javascript:;" class="dropdown-toggle info-number" data-toggle="dropdown" aria-expanded="false">
  <i class="fa fa-bell fa-2x"></i>
  <span class="badge bg-green">{$records_qty}</span>
</a>
<ul id="menu1" class="dropdown-menu list-unstyled msg_list" role="menu">
{foreach item=event from=$events}
<li>
    <a {$event.view_href}>
    <span class="image"><span class="fa fa-{if $event.icon}{$event.icon}{else}home{/if} fa-3x"></span></span>
    <span>
      <span>{$event.category}</span>
      <span class="time">{$event.time}</span>
    </span>
    </a>
    <span class="message">
      {$event.title}
    </span>
</li>
{/foreach}
<li>
  <div class="text-center">
    <a {$href}>
      <strong>{$status}</strong>
      <i class="fa fa-angle-right"></i>
    </a>
  </div>
</li>
</ul>
