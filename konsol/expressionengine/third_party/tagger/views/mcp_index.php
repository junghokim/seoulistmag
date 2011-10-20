<?php echo $this->view('mcp_header'); ?>

<?php if ($total_tags < 1): ?>
<p><?=lang('tagger:no_tags_used')?></p>
<?php else:?>

<table class="mainTable">
	<thead>
		<tr>
			<th><?=lang('tagger:tag_name')?></th>
			<th><?=lang('tagger:total_entries')?></th>
			<th><?=lang('tagger:groups')?></th>
			<th><?=lang('tagger:delete')?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach($tags as $tag):?>
		<tr rel="<?=$tag->tag_id?>">
			<td><a href="<?=$base_url?>&method=edit_tag&tag_id=<?=$tag->tag_id?>"><?=$tag->tag_name?></a></td>
			<td><?=$tag->total_entries?></td>
			<td>
				<select class="TaggerGroupSelect" multiple>
					<?php foreach ($groups as $group):?>
					<?php
						$checked = '';
						if (isset($groups_entries[$tag->tag_id]) == TRUE && in_array($group->group_id, $groups_entries[$tag->tag_id]))
						{
							$checked = 'selected';
						}
					?>
					<option value="<?=$group->group_id?>" <?=$checked?>><?=$group->group_title?></option>
					<?php endforeach;?>
				</select>

				<?php foreach ($groups as $group):?>
				<?php
					$checked = '';
					if (isset($groups_entries[$tag->tag_id]) == TRUE && in_array($group->group_id, $groups_entries[$tag->tag_id]))
					{
						$checked = 'selected';
					}
				?>
				<?php if ($checked):?><small><?=$group->group_title?></small>, <?php endif;?>
				<?php endforeach;?>

			</td>
			<td><a href="<?=$base_url?>&method=update_tag&delete=yes&tag_id=<?=$tag->tag_id?>" class="gIcon DeleteIcon"></a></td>
		</tr>
	<?php endforeach;?>
	</tbody>
</table>

<?php endif;?>