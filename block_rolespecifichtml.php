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

require_once($CFG->dirroot.'/lib/filelib.php');

class block_rolespecifichtml extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_rolespecifichtml');
    }

    public function applicable_formats() {
        return array('all' => true, 'admin' => false, 'my' => false);
    }

    public function specialization() {
        $this->title = isset($this->config->title) ? format_string($this->config->title) : format_string(get_string('newhtmlblock', 'block_rolespecifichtml'));
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function get_content() {
        global $COURSE, $CFG, $USER;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $filteropt = new stdClass;
        $filteropt->overflowdiv = true;
        if ($this->content_is_trusted()) {
            // Fancy html allowed only on course, category and system blocks.
            $filteropt->noclean = true;
        }

        $this->content = new stdClass;

        $roleids = null;
        if (@$this->config->display == 'highest') {
            $highest = $this->get_highest_role();
            if ($highest) {
                $roleids = array($highest);
            }
        } else {
            $roleids = $this->get_user_roles();
        }

        $this->content = new stdClass;
        $this->content->text = '';

        $tk = "text_all";
        if (!isset($this->config)) {
            $this->config = new StdClass();
        }

        $this->config->$tk = file_rewrite_pluginfile_urls(@$this->config->$tk, 'pluginfile.php', $this->context->id, 'block_rolespecifichtml', 'content_all', null);
        if (is_array($this->config->$tk)) {
            $arr = $this->config->$tk;
            $this->content->text .= !empty($arr['text']) ? format_text($arr['text'], $arr['format'], $filteropt) : '';
        } else {
            $this->content->text .= !empty($this->config->$tk) ? format_text($this->config->$tk, FORMAT_HTML, $filteropt) : '';
        }

        if (!empty($roleids)) {
            foreach ($roleids as $roleid) {
                $tk = "text_$roleid";
                $this->config->$tk = file_rewrite_pluginfile_urls(@$this->config->$tk, 'pluginfile.php', $this->context->id, 'block_rolespecifichtml', 'content_' . $roleid, null);
                if (is_array($this->config->$tk)) {
                    $arr = $this->config->$tk;
                    $this->content->text .= !empty($arr['text']) ? format_text($arr['text'], $arr['format'], $filteropt) : '';
                } else {
                    $this->content->text .= !empty($this->config->$tk) ? format_text($this->config->$tk, FORMAT_HTML, $filteropt) : '';
                }
            }
        }
        $this->content->footer = '';

        unset($filteropt); // Memory footprint.

        if (empty($this->content->text)) $this->content->text = '&nbsp;';

        return $this->content;
    }

    /**
     * Serialize and store config data
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB, $COURSE, $USER;

        $context = context::instance_by_id($this->instance->parentcontextid);

        $config = clone($data);
        // Move embedded files into a proper filearea and adjust HTML links to match.
        $config->text_all = file_save_draft_area_files($data->text_all['itemid'], $this->context->id, 'block_rolespecifichtml', 'content_all', 0, array('subdirs' => true), $data->text_all['text']);
        $config->format_all = $data->text_all['format'];

        if (!$context) {
            $contextlevel = CONTEXT_COURSE;
        } else {
            $contextlevel = $context->contextlevel;
        }
        $roles = get_roles_for_contextlevels($contextlevel);

        if (!empty($roles)) {
            foreach (array_values($roles) as $rid) {
                $tk = 'text_'.$rid;
                $fk = 'format_'.$rid;
                $config->{$tk} = file_save_draft_area_files(@$data->{$tk}['itemid'], $this->context->id, 'block_rolespecifichtml', 'content_' . $rid, 0, array('subdirs' => true), @$data->{$tk}['text']);
                $config->{$fk} = @$data->{$tk}['format'];
            }
        }

        parent::instance_config_save($config, $nolongerused);
    }

    public function instance_delete() {
        global $DB;
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'block_rolespecifichtml');
        return true;
    }

    public function content_is_trusted() {
        global $SCRIPT;

        if (!$context = context::instance_by_id($this->instance->parentcontextid)) {
            return false;
        }
        // Find out if this block is on the profile page.
        if ($context->contextlevel == CONTEXT_USER) {
            if ($SCRIPT === '/my/index.php') {
                /*
                 * this is exception - page is completely private, nobody else may see content there
                 * that is why we allow JS here
                 */
                return true;
            } else {
                // No JS on public personal pages, it would be a big security issue.
                return false;
            }
        }

        return true;
    }

    /**
     * The block should only be dockable when the title of the block is not empty
     * and when parent allows docking.
     *
     * @return bool
     */
    public function instance_can_be_docked() {
        return (!empty($this->config->title) && parent::instance_can_be_docked());
    }

    /*
     * Hide the title bar when none set..
     */
    public function hide_header() {
        return empty($this->config->title);
    }

    /**
     * get highest role in context
     */
    public function get_highest_role() {
        global $COURSE, $USER;

        if (empty($this->config) || $this->config->context == 'course') {
            $context = context_course::instance($COURSE->id);
        } else {
            $context = context_system::instance();
        }
        $roles = get_user_roles($context, $USER->id, false);

        if ($roles) {
            if ($highest = array_shift($roles)) {
                return $highest->roleid;
            }
        }
        return 0;
    }

    /**
     * get all roles in context
     */
    public function get_user_roles() {
        global $COURSE, $USER;

        if (empty($this->config) || $this->config->context == 'course') {
            $context = context_course::instance($COURSE->id);
        } else {
            $context = context_system::instance();
        }
        $roles = get_user_roles($context, $USER->id, false);

        $roleids = array();
        if (!empty($roles)) {
            foreach($roles as $r) {
                $roleids[] = $r->roleid;
            }
            return $roleids;
        }
        return null;
    }
}
