<?php
/**
 * Action called by AJAX periodic auto saving when editing.
 *
 * @package Blog
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Curverider Ltd
 * @copyright Curverider Ltd 2008-2010
 * @link http://elgg.org/
 */

$guid = get_input('guid');
$user = get_loggedin_user();
$title = get_input('title');
$description = get_input('description');
$excerpt = get_input('excerpt');

// because get_input() doesn't use the default if the input is ''
if (empty($excerpt)) {
	$excerpt = $description;
}

// store errors to pass along
$error = FALSE;

if ($title && $description) {

	if ($guid) {
		$entity = get_entity($guid);
		if (elgg_instanceof($entity, 'object', 'blog') && $entity->canEdit()) {
			$blog = $entity;
		} else {
			$error = elgg_echo('blog:error:post_not_found');
		}
	} else {
		$blog = new ElggObject();
		$blog->subtype = 'blog';

		// force draft and private for autosaves.
		$blog->status = 'unsaved_draft';
		$blog->access_id = ACCESS_PRIVATE;
		$blog->title = $title;
		$blog->description = $description;
		$blog->excerpt = blog_make_excerpt($excerpt);
		// must be present or doesn't show up when metadata sorting.
		$blog->publish_date = time();
		if (!$blog->save()) {
			$error = elgg_echo('blog:error:cannot_save');
		}
	}

	// creat draft annotation
	if (!$error) {
		// annotations don't have a "time_updated" so
		// we have to delete everything or the times are wrong.

		// don't save if nothing changed
		if ($auto_save_annotations = $blog->getAnnotations('blog_auto_save', 1)) {
			$auto_save = $auto_save_annotations[0];
		} else {
			$auto_save == FALSE;
		}

		if (!$auto_save) {
			$annotation_id = $blog->annotate('blog_auto_save', $description);
		} elseif ($auto_save instanceof ElggAnnotation && $auto_save->value != $description) {
			$blog->clearAnnotations('blog_auto_save');
			$annotation_id = $blog->annotate('blog_auto_save', $description);
		} elseif ($auto_save instanceof ElggAnnotation && $auto_save->value == $description) {
			// this isn't an error because we have an up to date annotation.
			$annotation_id = $auto_save->id;
		}

		if (!$annotation_id) {
			$error = elgg_echo('blog:error:cannot_auto_save');
		}
	}
} else {
	$error = elgg_echo('blog:error:missing:description');
}

if ($error) {
	$json = array('success' => FALSE, 'message' => $error);
	echo json_encode($json);
} else {
	$msg = elgg_echo('blog:message:saved');
	$json = array('success' => TRUE, 'message' => $msg, 'guid' => $blog->getGUID());
	echo json_encode($json);
}