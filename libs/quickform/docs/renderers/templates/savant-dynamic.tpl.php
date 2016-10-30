<?=$this->form['javascript']?>
<form <?=$this->form['attributes']?>>
<? if (!empty($this->form['hidden'])) : ?>
<div style="display:none;"><?=$this->form['hidden']?></div>
<? endif; ?>
<table class="qfmaintable" cellpadding="0" cellspacing="0" border="0">
<? foreach($this->form['sections'] as $section): ?>
<? if (isset($section['header'])) : ?>
  <tr><td colspan="2" class="qfheader"><?php echo $section['header'] ?></td></tr>
<? endif; ?>
<? foreach ($section['elements'] as $element) : ?>
  <tr>
<? if (!empty($element['style'])) : ?>   
   <? if ($element['style'] === 'buttons' && $element['type'] === 'group') : ?>
    <td colspan="2" class="qfbuttons"><? foreach ($element['elements'] as $groupElement): ?><?=$groupElement['html']?><?=$groupElement['separator']?><? endforeach; ?></td>
   <? elseif ($element['style'] === 'table' && $element['type'] === 'group') : ?>
    <td class="qflabel"><? if ($element['required']): ?><span class="qfrequired">*</span><? endif; ?><label for="<? echo !empty($element['id']) ? $element['id'] : $element['name']; ?>"><?=$element['label'] ?></label></td>
    <td class="qfelement"><? if (!empty($element['error'])) : ?><div class="error"><?=$element['error']?></div><? endif; ?>
      <table cellpadding="2" cellspacing="0" border="0">
      <tr><? foreach ($element['elements'] as $groupElement): ?><td><?=$groupElement['html']?><br />
      <? if (!empty($groupElement['id'])) : ?><label for="<?=$groupElement['id']?>"><?=$groupElement['label']?></label><? else : ?><?=$groupElement['label']?><? endif; ?><? if ($groupElement['required']): ?><span class="qfrequired">*</span><? endif; ?></td>
<? endforeach; ?>
      </tr></table></td>
   <? elseif ($element['style'] === 'section') : ?>
    <td colspan="2" class="qfsection"><?=$element['label']?><?=$element['html']?></td>
   <? endif; ?>
<? elseif ($element['type'] === 'group') : ?>
    <td class="qflabel"><? if ($element['required']): ?><span class="qfrequired">*</span><? endif; ?><label for="<? echo !empty($element['id']) ? $element['id'] : $element['name']; ?>"><?=$element['label'] ?></label></td>
    <td class="qfelement"><? if (!empty($element['error'])) : ?><div class="error"><?=$element['error']?></div><? endif; ?>
<? foreach ($element['elements'] as $groupElement): ?><?=$groupElement['html']?><?=$groupElement['separator']?>
<? endforeach; ?>
    </td>
<? else : ?>
    <td class="qflabel"><? if ($element['required']): ?><span class="qfrequired">*</span><? endif; ?><? if (!empty($element['id'])) : ?><label for="<?=$element['id']?>"><?=$element['label'] ?></label><? else : ?><?=$element['label'] ?><? endif; ?></td>
    <td class="qfelement"><? if (!empty($element['error'])) : ?><div class="error"><?=$element['error']?></div><? endif; ?><?=$element['html']?></td>
<? endif; ?>
  </tr>
<? endforeach; ?>
<? endforeach; ?>
<? if (!empty($this->form['requirednote'])) : ?>
  <tr><td colspan="2"><?=$this->form['requirednote']?></td></tr>
<? endif; ?>
</table>
</form>
