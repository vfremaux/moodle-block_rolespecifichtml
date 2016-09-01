<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * @package   block_rolespecifichtml
 * @category  blocks
 * @author    Valery Fremaux (valery.fremaux@gmail.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_rolespecifichtml_edit_form extends block_edit_form {

    var $context;

    protected function specific_definition($mform) {
        global $COURSE, $DB;

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_rolespecifichtml'));
        $mform->setType('config_title', PARAM_MULTILANG);

        $contextopts['course'] = get_string('course', 'block_rolespecifichtml');
        $contextopts['system'] = get_string('system', 'block_rolespecifichtml');
        $mform->addElement('select', 'config_context', get_string('configcontext', 'block_rolespecifichtml'), $contextopts);
        $mform->setType('config_context', PARAM_TEXT);
        $mform->setDefault('config_context', 'course');
        $mform->addHelpButton('config_context', 'context', 'block_rolespecifichtml');

        $displayopts['allmatches'] = get_string('showallmatches', 'block_rolespecifichtml');
        $displayopts['highest'] = get_string('showhighest', 'block_rolespecifichtml');
        $mform->addElement('select', 'config_display', get_string('configdisplay', 'block_rolespecifichtml'), $displayopts);
        $mform->setDefault('config_display', 'highest');

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true, 'context' => $this->block->context);
        $mform->addElement('editor', 'config_text_all', get_string('configcontentforall', 'block_rolespecifichtml'), null, $editoroptions);
        $mform->setType('config_text_all', PARAM_RAW); // XSS is prevented when printing the block contents and serving files

        // TODO : restrict to endorsable roles...
        // TODO program roles available on context

        if (empty($this->context)) {
            $this->context = context_course::instance($COURSE->id);
        }

        /*
        $sql = "
            SELECT DISTINCT
                r.*
            FROM
                {role} r,
                {role_context_levels} rcl
            WHERE
                r.id = rcl.roleid AND
                contextlevel = $this->contextlevel
        ";
        $roles = $DB->get_records_sql($sql);
        */
        $contextroles = get_roles_for_contextlevels($this->context->contextlevel);
        $roles = role_fix_names(get_all_roles(), $this->context, ROLENAME_ORIGINAL);

        $rids = array();
        foreach ($roles as $r) {
            if (!in_array($r->id, $contextroles)) {
                continue;
            }
            $mform->addElement('editor', 'config_text_'.$r->id, get_string('configcontent', 'block_rolespecifichtml', $r->localname), null, $editoroptions);
            $mform->setType('config_text_'.$r->id, PARAM_RAW); // XSS is prevented when printing the block contents and serving files
            $rids[] = $r->id;
        }

        $mform->addElement('hidden', 'config_textids');
        $mform->setType('config_textids', PARAM_TEXT);
        $mform->setDefault('config_textids', implode(',', $rids));

    }

    function set_data($defaults, &$files = null) {
        global $COURSE, $DB;

        $this->context = (empty($this->block->config) || $this->block->config->context == 'course') ? context_course::instance($COURSE->id) : context_system::instance() ;
        if (empty($this->block->config) || $this->block->config->context == 'course') {
            $contextlevel = CONTEXT_COURSE;
            $context = context_course::instance($COURSE->id);
        } else {
            $contextlevel = CONTEXT_SYSTEM;
            $context = context_system::instance();
        }

        $contextroles = get_roles_for_contextlevels($contextlevel);
        $roles = role_fix_names(get_all_roles(), $context, ROLENAME_ORIGINAL);

        if (!empty($this->block->config) && is_object($this->block->config)) {

            // Draft file handling for all.
            $text_all = $this->block->config->text_all;
            $draftid_editor = file_get_submitted_draft_itemid('config_text_all');

            if (empty($text_all)) {
                $currenttext = '';
            } else {
                $currenttext = $text_all;
            }

            $defaults->config_text_all['text'] = file_prepare_draft_area($draftid_editor, $this->block->context->id, 'block_rolespecifichtml', 'content', 0, array('subdirs' => true), $currenttext);
            $defaults->config_text_all['itemid'] = $draftid_editor;
            $defaults->config_text_all['format'] = @$this->block->config->format;

            if (!empty($roles)) {
                foreach ($roles as $r) {
                    if (!in_array($r->id, $contextroles)) {
                        continue;
                    }
                    // Draft file handling for each.
                    $textvar = 'text_'.$r->id;
                    $configtextvar = 'config_text_'.$r->id;
                    // $text_0 = @$this->block->config->$textvar;
                    $draftid_editor = file_get_submitted_draft_itemid($configtextvar);

                    if (empty($this->block->config->$textvar)) {
                        $currenttext = '';
                    } else {
                        $currenttext = $this->block->config->$textvar;
                    }

                    $defaults->{$configtextvar}['text'] = file_prepare_draft_area($draftid_editor, $this->block->context->id, 'block_rolespecifichtml', 'content', 0, array('subdirs' => true), $currenttext);
                    $defaults->{$configtextvar}['itemid'] = $draftid_editor;
                    $defaults->{$configtextvar}['format'] = @$this->block->config->format;
                }
            }
        } else {
            $text_all = '';
            if (!empty($roles)) {
                foreach ($roles as $r) {
                    $textvar = 'text_'.$r->id;
                    $$textvar = '';
                }
            }
        }

        if (!$this->block->user_can_edit() && !empty($this->block->config->title)) {
            // If a title has been set but the user cannot edit it format it nicely.
            $title = $this->block->config->title;
            $defaults->config_title = format_string($title, true, $this->page->context);
            // Remove the title from the config so that parent::set_data doesn't set it.
            unset($this->block->config->title);
        }

        // Have to delete text here, otherwise parent::set_data will empty content of editor
        unset($this->block->config->text_all);
        if (!empty($roles)) {
            foreach ($roles as $r) {
                $textvar = 'text_'.$r->id;
                unset($this->block->config->{$textvar});
            }
        }
        parent::set_data($defaults, $files);

        // Restore $text
        if (!isset($this->block->config)) {
            $this->block->config = new StdClass();
        }
        $this->block->config->text_all = $text_all;
        if (!empty($roles)) {
            foreach ($roles as $r) {
                $textvar = 'text_'.$r->id;
                $this->block->config->{$textvar} = @$$textvar;
            }
        }

        if (isset($title)) {
            // Reset the preserved title.
            $this->block->config->title = $title;
        }

    }
}
