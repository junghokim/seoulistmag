<?php echo $this->view('mcp_header'); ?>

<?=form_open($base_url_short.AMP.'method=update_tag')?>
<table class="mainTable">
	<thead>
		<tr>
			<th width="40%"><?=lang('tagger:question')?></th>
			<th width="60%"><?=lang('tagger:answer')?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><strong><?=lang('tagger:tag_name')?></strong></td>
			<td><input name="tag_name" type="text" value="<?=$tag_name?>"/></td>
		</tr>
	</tbody>
</table>

<input name="tag_id" type="hidden" value="<?=$tag_id?>" />

<input name="submit" class="submit" type="submit" value="Save"/>

<?=form_close()?>