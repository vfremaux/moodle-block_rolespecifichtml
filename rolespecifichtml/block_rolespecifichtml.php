<?php //$Id: block_rolespecifichtml.php,v 1.1 2012-03-22 13:44:32 vf Exp $

class block_rolespecifichtml extends block_base {

    function init() {
        $this->title = get_string('blockname', 'block_rolespecifichtml');
        $this->version = 2012032000;
    }

    function applicable_formats() {
        return array('all' => true);
    }

    function specialization() {
        $this->title = isset($this->config->title) ? format_string($this->config->title) : format_string(get_string('newhtmlblock', 'block_rolespecifichtml'));
    }

    function instance_allow_multiple() {
        return true;
    }

    function get_content() {
        if ($this->content !== NULL) {
            return $this->content;
        }

        if (!empty($this->instance->pinned) or $this->instance->pagetype === 'course-view') {
            // fancy html allowed only on course page and in pinned blocks for security reasons
            $filteropt = new stdClass;
            $filteropt->noclean = true;
        } else {
            $filteropt = null;
        }
        
        $roleid = $this->get_highest_role();

        $this->content = new stdClass;
        $textkey = "text_all";
        $this->content->text .= !empty($this->config->$textkey) ? format_text($this->config->$textkey, FORMAT_HTML, $filteropt) : '';
        $textkey = "text_$roleid";
        $this->content->text = isset($this->config->$textkey) ? format_text($this->config->$textkey, FORMAT_HTML, $filteropt) : '';
        $this->content->footer = '';

        unset($filteropt); // memory footprint

        return $this->content;
    }

    /**
     * Will be called before an instance of this block is backed up, so that any links in
     * any links in any HTML fields on config can be encoded.
     * @return string
     */
    function get_backup_encoded_config() {
        /// Prevent clone for non configured block instance. Delegate to parent as fallback.
        if (empty($this->config)) {
            return parent::get_backup_encoded_config();
        }
        $data = clone($this->config);
        foreach($data->textids as $rid){
        	$textkey = "text_$rid";
	        $data->$textkey = backup_encode_absolute_links($data->$textkey);
	    }
        return base64_encode(serialize($data));
    }

    /**
     * This function makes all the necessary calls to {@link restore_decode_content_links_worker()}
     * function in order to decode contents of this block from the backup 
     * format to destination site/course in order to mantain inter-activities 
     * working in the backup/restore process. 
     * 
     * This is called from {@link restore_decode_content_links()} function in the restore process.
     *
     * NOTE: There is no block instance when this method is called.
     *
     * @param object $restore Standard restore object
     * @return boolean
     **/
    function decode_content_links_caller($restore) {
        global $CFG;

        if ($restored_blocks = get_records_select("backup_ids", "table_name = 'block_instance' AND backup_code = $restore->backup_unique_code AND new_id > 0", "", "new_id")) {
            $restored_blocks = implode(',', array_keys($restored_blocks));
            $sql = "SELECT bi.*
                      FROM {$CFG->prefix}block_instance bi
                           JOIN {$CFG->prefix}block b ON b.id = bi.blockid
                     WHERE b.name = 'rolespecifichtml' AND bi.id IN ($restored_blocks)"; 

            if ($instances = get_records_sql($sql)) {
                foreach ($instances as $instance) {
                    $blockobject = block_instance('rolespecifichtml', $instance);
                    foreach($blockobject->config->textids as $rid){
	                    $blockobject->config->$textkey = restore_decode_absolute_links($blockobject->config->$textkey);
	                    $blockobject->config->$textkey = restore_decode_content_links_worker($blockobject->config->$textkey, $restore);
	                }
                    $blockobject->instance_config_commit($blockobject->pinned);
                }
            }
        }

        return true;
    }

    /*
     * Hide the title bar when none set..
     */
    function hide_header(){
        return empty($this->config->title);
    }

	/** 
	* get highest role in course context
	*/
    function get_highest_role(){
    	global $COURSE, $USER;
    	
    	$context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
    	if ($roles = get_user_roles($context, $USER->id, false)){
    		$highest = next($roles);
    		return $highest->id;
    	}
    	return 0;
    }
}
?>
