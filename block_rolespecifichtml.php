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

        $this->content->text = '';

        $roleid = $this->get_highest_role();

        $this->content = new stdClass;
        $tk = "text_all";
        $this->config->$tk = file_rewrite_pluginfile_urls(@$this->config->$tk, 'pluginfile.php', $this->context->id, 'block_rolespecifichtml', 'content', NULL);
        $this->content->text .= !empty($this->config->$tk) ? format_text($this->config->$tk, FORMAT_HTML, $filteropt) : '';
        $textkey = "text_$roleid";
         $this->config->$tk = file_rewrite_pluginfile_urls(@$this->config->$tk, 'pluginfile.php', $this->context->id, 'block_rolespecifichtml', 'content', NULL);
        $this->content->text = isset($this->config->$tk) ? format_text($this->config->$tk, FORMAT_HTML, $filteropt) : '';
        $this->content->footer = '';

        unset($filteropt); // Memory footprint.

        if (empty($this->content->text)) $this->content->text = '&nbsp;';

        return $this->content;
    }

    /**
     * Serialize and store config data
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB, $COURSE;

        $config = clone($data);
        // Move embedded files into a proper filearea and adjust HTML links to match.
        $config->text_all = file_save_draft_area_files($data->text_all['itemid'], $this->context->id, 'block_rolespecificthtml', 'content', 0, array('subdirs' => true), $data->text_all['text']);
        $config->format_all = $data->text_all['format'];

        $groups = groups_get_all_groups($COURSE->id);
        if (!empty($groups)){
            foreach($groups as $g){
                $textkey = 'text_'.$g->id;
                $formatkey = 'format_'.$g->id;
                $config->{$textkey} = file_save_draft_area_files(@$data->{$textkey}['itemid'], $this->context->id, 'block_rolespecificthtml', 'content', 0, array('subdirs' => true), @$data->{$textkey}['text']);
                $config->{$formatkey} = @$data->{$textkey}['format'];
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
    * get highest role in course context
    */
    public function get_highest_role() {
        global $COURSE, $USER;
        
        if (empty($this->config) || $this->config->context == 'course') {
            $context = context_course::instance($COURSE->id);
        } else {
            $context = context_system::instance();
        }
        if ($roles = get_user_roles($context, $USER->id, false)) {
            if ($highest = next($roles)) {
                return $highest->id;
            }
        }
        return 0;
    }
}
