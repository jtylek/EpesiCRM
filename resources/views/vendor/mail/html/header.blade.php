@props(['url'])
<tr>
<td class="header" align="center">
<table align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:570px;margin:0 auto;">
<tr>
<td align="center" style="padding:20px 0 0;">
@php($logo = \App\Support\Logo::mail())
<a href="{{ $url }}" style="display: block;">
<img src="{{ $logo['url'] }}" class="logo" alt="{{ $logo['alt'] }}" width="570" style="width:100%;max-width:570px;height:auto;border:0;display:block;">
</a>
</td>
</tr>
</table>
</td>
</tr>
