<?php if ($dupe_field == TRUE): ?>
<span style="font-weight:bold; color:red;"> <?=lang('tagger:dupe_field')?> </span>
<input name="field_id_<?=$field_id?>[skip]" type="hidden" value="y" />
<?php else: ?>

<div id="TaggerBox" rel="<?=$field_id?>">

	<div class="LeftSection">
		<div class="TagBox sBox">
			<div class="Head"><?=lang('tagger:insert_tags')?> <br /> <span><?=lang('tagger:insert_tags_exp')?></span> </div>
			<div class="Content"> <input type="text" class="InstantInsert" /> </div>
		</div>

		<div class="TagBox sBox">
			<div class="Head"><?=lang('tagger:search_tags')?> <br /> <span><?=lang('tagger:search_tags_exp')?></span></div>
			<div class="Content"> <input type="text" class="TagSearch" /> </div>
		</div>

		<div class="clear"></div>

		<div class="TagBox AssignedTags">
			<div class="Head"><?=lang('tagger:assigned_tags')?></div>
			<div class="Content AssignedTags">
				<?php if (empty($assigned_tags) == TRUE):?> <span class="NoTagsAssigned"><?=lang('tagger:no_assigned_tags')?></span> <?php endif;?>
				<?php foreach($assigned_tags as $tag):?> <div class="tag"><div><?=$tag?><input type="hidden" value="<?=$tag?>" name="<?=$field_name?>[tags][]"> <a href="#"></a></div></div>  <?php endforeach;?>
				<div class="clear"></div>
			</div>
		</div>
		<div class="clear"></div>
	</div>

	<div class="RightSection">
		<div class="TagBox">
			<div class="Head"><?=lang('tagger:most_used_tags')?></div>
			<div class="Content MostUsedTags">
				<?php if (empty($most_used_tags) == TRUE):?> <?=lang('tagger:no_tags_used')?> <?php endif;?>
				<?php foreach($most_used_tags as $tag):?> <a href="#" class="tag"><?=$tag?></a> <?php endforeach;?>
				<div class="clear"></div>
			</div>
		</div>
	</div>

	<input name="field_id_<?=$field_id?>[skip]" type="hidden" value="n" />
	<input name="field_ft_<?=$field_id?>" value="none" type="hidden"/>

	<div class="clear"></div>
</div>
<?php endif; ?>