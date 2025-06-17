<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

use core_completion\progress;
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');


defined('MOODLE_INTERNAL') || die();



function debug($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    #echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
    echo "Debug Objects: " . $output . "";
}


/**
 * Class which contains the implementations of the added functions.
 *
 * @package local_sync_service
 * @copyright 2022 Daniel Schröter & 2025 Coursensu (extending local_sync_serivce)
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_sync_service_external extends external_api {
    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_add_new_section_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value( PARAM_TEXT, 'id of course' ),
                'sectionname' => new external_value( PARAM_TEXT, 'name of section' ),
                'sectionnum' => new external_value( PARAM_TEXT, 'position of the new section ' ),
            )
        );
    }

    /**
     * Creating and positioning of a new section.
     *
     * @param $courseid The course id.
     * @param $sectionname Name of the new section.
     * @param $sectionnum The position of the section inside the course, will be placed before a exisiting section with same sectionnum.
     * @return $update Message: Successful.
     */
    public static function local_sync_service_add_new_section($courseid, $sectionname, $sectionnum) {
        global $DB, $CFG;
        // Parameter validation.
        $params = self::validate_parameters(
        self::local_sync_service_add_new_section_parameters(),
            array(
                'courseid' => $courseid,
                'sectionname' => $sectionname,
                'sectionnum' => $sectionnum,
            )
        );

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Required permissions.
        require_capability('block/section_links:addinstance', $context);

        $cw = course_create_section($params['courseid'], $params['sectionnum'], false);

        $section = $DB->get_record('course_sections', array('id' => $cw->id), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $section->course), '*', MUST_EXIST);

        $data['name'] = $params['sectionname'];

        course_update_section($course, $section, $data);

        $update = [
            'message' => 'Successful',
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_add_new_section_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
            )
        );
    }


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_add_new_course_module_url_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value( PARAM_TEXT, 'id of course' ),
                'sectionnum' => new external_value( PARAM_TEXT, 'relative number of the section' ),
                'urlname' => new external_value( PARAM_TEXT, 'displayed mod name' ),
                'url' => new external_value( PARAM_TEXT, 'url to insert' ),
                'time' => new external_value( PARAM_TEXT, 'defines the mod. visibility', VALUE_DEFAULT, null ),
                'visible' => new external_value( PARAM_TEXT, 'defines the mod. visibility' ),
                'beforemod' => new external_value( PARAM_TEXT, 'mod to set before', VALUE_DEFAULT, null ),
            )
        );
    }


    /**
     * Method to create a new course module containing a url.
     *
     * @param $courseid The course id.
     * @param $sectionnum The number of the section inside the course.
     * @param $urlname Displayname of the Module.
     * @param $url Url to publish.
     * @param $time availability time.
     * @param $visible visible for course members.
     * @param $beforemod Optional parameter, a Module where the new Module should be placed before.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_add_new_course_module_url($courseid, $sectionnum, $urlname, $url, $time = null, $visible, $beforemod = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/url' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_add_new_course_module_url_parameters(),
            array(
                'courseid' => $courseid,
                'sectionnum' => $sectionnum,
                'urlname' => $urlname,
                'url' => $url,
                'time' => $time,
                'visible' => $visible,
                'beforemod' => $beforemod,
            )
        );

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/url:addinstance', $context);

        $instance = new \stdClass();
        $instance->course = $params['courseid'];
        $instance->name = $params['urlname'];
        $instance->intro = null;
        $instance->introformat = \FORMAT_HTML;
        $instance->externalurl = $params['url'];
        $instance->id = url_add_instance($instance, null);

        $modulename = 'url';

        $cm = new \stdClass();
        $cm->course     = $params['courseid'];
        $cm->module     = $DB->get_field( 'modules', 'id', array('name' => $modulename) );
        $cm->instance   = $instance->id;
        $cm->section    = $params['sectionnum'];
        if (!is_null($params['time'])) {
            $cm->availability = "{\"op\":\"&\",\"c\":[{\"type\":\"date\",\"d\":\">=\",\"t\":" . $params['time'] . "}],\"showc\":[" . $params['visible'] . "]}";
        } else if ( $params['visible'] === 'false' ) {
            $cm->visible = 0;
        }
  
        $cm->id = add_course_module( $cm );
 
        $cmid = $cm->id;

        course_add_cm_to_section($params['courseid'], $cmid, $params['sectionnum'], $params['beforemod']);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_add_new_course_module_url_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }

    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_add_new_course_module_resource_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value( PARAM_TEXT, 'id of course' ),
                'sectionnum' => new external_value( PARAM_TEXT, 'relative number of the section' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of the upload' ),
                'displayname' => new external_value( PARAM_TEXT, 'displayed mod name' ),
                'time' => new external_value( PARAM_TEXT, 'defines the mod. availability', VALUE_DEFAULT, null ),
                'visible' => new external_value( PARAM_TEXT, 'defines the mod. visibility' ),
                'beforemod' => new external_value( PARAM_TEXT, 'mod to set before', VALUE_DEFAULT, null ),
            )
        );
    }

    /**
     * Method to create a new course module containing a file.
     *
     * @param $courseid The course id.
     * @param $sectionnum The number of the section inside the course.
     * @param $itemid File to publish.
     * @param $displayname Displayname of the Module.
     * @param $time availability time.
     * @param $visible visible for course members.
     * @param $beforemod Optional parameter, a Module where the new Module should be placed before.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_add_new_course_module_resource($courseid, $sectionnum, $itemid, $displayname, $time = null, $visible, $beforemod = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/resource' . '/lib.php');
        require_once($CFG->dirroot . '/availability/' . '/condition' . '/date' . '/classes' . '/condition.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_add_new_course_module_resource_parameters(),
            array(
                'courseid' => $courseid,
                'sectionnum' => $sectionnum,
                'itemid' => $itemid,
                'displayname' => $displayname,
                'time' => $time,
                'visible' => $visible,
                'beforemod' => $beforemod,
            )
        );

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/resource:addinstance', $context);

        $modulename = 'resource';

        $cm = new \stdClass();
        $cm->course     = $params['courseid'];
        $cm->module     = $DB->get_field('modules', 'id', array( 'name' => $modulename ));
        $cm->section    = $params['sectionnum'];
        if (!is_null($params['time'])) {
            $cm->availability = "{\"op\":\"&\",\"c\":[{\"type\":\"date\",\"d\":\">=\",\"t\":" . $params['time'] . "}],\"showc\":[" . $params['visible'] . "]}";
        } else if ( $params['visible'] === 'false' ) {
            $cm->visible = 0;
        }
        $cm->id = add_course_module($cm);
        $cmid = $cm->id;

        $instance = new \stdClass();
        $instance->course = $params['courseid'];
        $instance->name = $params['displayname'];
        $instance->intro = null;
        $instance->introformat = \FORMAT_HTML;
        $instance->coursemodule = $cmid;

        $instance->files = $params['itemid'];
        $instance->id = resource_add_instance($instance, null);

        course_add_cm_to_section($params['courseid'], $cmid, $params['sectionnum'], $params['beforemod']);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_add_new_course_module_resource_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }

    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_move_module_to_specific_position_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'sectionid' => new external_value( PARAM_TEXT, 'relative number of the section' ),
                'beforemod' => new external_value( PARAM_TEXT, 'mod to set before', VALUE_DEFAULT, null ),
            )
        );
    }

    /**
     * Method to position an existing course module.
     *
     * @param $cmid The Module to move.
     * @param $sectionid The id of the section inside the course.
     * @param $beforemod Optional parameter, a Module where the new Module should be placed before.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_move_module_to_specific_position($cmid, $sectionid, $beforemod = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_move_module_to_specific_position_parameters(),
            array(
                'cmid' => $cmid,
                'sectionid' => $sectionid,
                'beforemod' => $beforemod,
            )
        );

        // Ensure the current user has required permission.
        $modcontext = context_module::instance( $params['cmid'] );
        self::validate_context( $modcontext );

        $cm = get_coursemodule_from_id('', $params['cmid']);

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($cm->course);
        self::validate_context($context);

        // Required permissions.
        require_capability('moodle/course:movesections', $context);

        $section = $DB->get_record('course_sections', array( 'id' => $params['sectionid'], 'course' => $cm->course ));

        moveto_module($cm, $section, $params['beforemod']);

        $update = [
            'message' => 'Successful',
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_move_module_to_specific_position_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' )
            )
        );
    }

    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_add_new_course_module_directory_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value( PARAM_TEXT, 'id of course' ),
                'sectionnum' => new external_value( PARAM_TEXT, 'relative number of the section' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of the upload' ),
                'displayname' => new external_value( PARAM_TEXT, 'displayed mod name' ),
                'time' => new external_value( PARAM_TEXT, 'defines the mod. visibility', VALUE_DEFAULT, null ),
                'visible' => new external_value( PARAM_TEXT, 'defines the mod. visibility' ),
                'beforemod' => new external_value( PARAM_TEXT, 'mod to set before', VALUE_DEFAULT, null ),
            )
        );
    }

    /**
     * Method to create a new course module of type folder.
     *
     * @param $courseid The course id.
     * @param $sectionnum The number of the section inside the course.
     * @param $displayname Displayname of the Module.
     * @param $itemid Files in same draft area to upload.
     * @param $time availability time.
     * @param $visible visible for course members.
     * @param $beforemod Optional parameter, a Module where the new Module should be placed before.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_add_new_course_module_directory($courseid, $sectionnum, $itemid, $displayname, $time = null, $visible, $beforemod = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/folder' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_add_new_course_module_directory_parameters(),
            array(
                'courseid' => $courseid,
                'sectionnum' => $sectionnum,
                'itemid' => $itemid,
                'displayname' => $displayname,
                'time' => $time,
                'visible' => $visible,
                'beforemod' => $beforemod,
            )
        );

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/folder:addinstance', $context);

        $modulename = 'folder';

        $cm = new \stdClass();
        $cm->course     = $params['courseid'];
        $cm->module     = $DB->get_field('modules', 'id', array( 'name' => $modulename ));
        $cm->section    = $params['sectionnum'];
        if (!is_null($params['time'])) {
            $cm->availability = "{\"op\":\"&\",\"c\":[{\"type\":\"date\",\"d\":\">=\",\"t\":" . $params['time'] . "}],\"showc\":[" . $params['visible'] . "]}";
        } else if ( $params['visible'] === 'false' ) {
            $cm->visible = 0;
        }
        $cm->id = add_course_module($cm);
        $cmid = $cm->id;

        $instance = new \stdClass();
        $instance->course = $params['courseid'];
        $instance->name = $params['displayname'];
        $instance->coursemodule = $cmid;
        $instance->introformat = FORMAT_HTML;
        $instance->intro = '<p>'.$params['displayname'].'</p>';
        $instance->files = $params['itemid'];
        $instance->id = folder_add_instance($instance, null);

        course_add_cm_to_section($params['courseid'], $cmid, $params['sectionnum'], $params['beforemod']);

        $update = [
            'message' => 'Successful',
            'id' => $instance->id,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_add_new_course_module_directory_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }

    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_add_files_to_directory_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value( PARAM_TEXT, 'id of course' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of the upload' ),
                'contextid' => new external_value( PARAM_TEXT, 'contextid of folder' ),
            )
        );
    }

    /**
     * This method implements the logic for the API-Call.
     *
     * @param $courseid The course id.
     * @param $itemid File(-s) to add.
     * @param $contextid Modules contextid.
     * @return $update Message: Successful.
     */
    public static function local_sync_service_add_files_to_directory($courseid, $itemid, $contextid) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/' . '/folder' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_add_files_to_directory_parameters(),
            array(
                'courseid' => $courseid,
                'itemid' => $itemid,
                'contextid' => $contextid,
            )
        );

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/folder:managefiles', $context);

        file_merge_files_from_draft_area_into_filearea($params['itemid'], $params['contextid'], 'mod_folder', 'content', 0);

        $update = [
            'message' => 'Successful',
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_add_files_to_directory_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                )
        );
    }

    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
public static function local_sync_service_add_new_course_module_page_parameters() {
    return new external_function_parameters(
        array(
            'courseid' => new external_value(PARAM_INT, 'Course ID'),                             // 1st param Moodle expects from client
            'sectionnum' => new external_value(PARAM_INT, '...'),                                  // 2nd
            'pagename' => new external_value(PARAM_TEXT, '...'),                                   // 3rd
            'pagecontent' => new external_value(PARAM_RAW, '...'),                                 // 4th
            'visible' => new external_value(PARAM_BOOL, '...'),                                    // 5th param Moodle expects from client
            'time' => new external_value(PARAM_INT, '...', VALUE_DEFAULT, null),                  // 6th param Moodle expects from client
            'beforemod' => new external_value(PARAM_INT, '...', VALUE_DEFAULT, null),             // 7th param Moodle expects from client
        )
    );
}


    /**
     * Method to create a new course module containing a Page.
     *
     * @param $courseid The course id.
     * @param $sectionnum The number of the section inside the course.
     * @param $urlname Displayname of the Module.
     * @param $content Content to publish.
     * @param $time availability time.
     * @param $visible visible for course members.
     * @param $beforemod Optional parameter, a Module where the new Module should be placed before.
     * @return $update Message: Successful and $cmid of the new Module.
     */
public static function local_sync_service_add_new_course_module_page(
    $courseid, $sectionnum, $pagename, $pagecontent, $visible,
    $time = null, $beforemod = null
) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/page/lib.php');

    // Parameter validation (ensure this is correct for the new PHP arg order)
    $params = self::validate_parameters(
        self::local_sync_service_add_new_course_module_page_parameters(),
        array(
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'pagename' => $pagename,
            'pagecontent' => $pagecontent,
            'visible' => $visible,
            'time' => $time,
            'beforemod' => $beforemod,
        )
    );

    // Ensure the current user has required permission in this course.
    $context = context_course::instance($params['courseid']);
    self::validate_context($context);
    require_capability('mod/page:addinstance', $context); // This capability check is fine here.

    // --- Step 1: Create the course_modules record first to get a $cmid ---
    $modulename = 'page';
    $cm = new \stdClass();
    $cm->course = $params['courseid'];
    $cm->module = $DB->get_field('modules', 'id', array('name' => $modulename), MUST_EXIST);
    $cm->section = $params['sectionnum'];
    $cm->visible = $params['visible'] ? 1 : 0;
    // $cm->instance will be 0 or not set initially. add_course_module handles this.
    // Availability logic
    if (!is_null($params['time'])) {
        $availabilitytime = (int)$params['time'];
        if ($availabilitytime <= 0 && $availabilitytime !== 0) { // Allow 0 if it means "no restriction but time was provided"
             throw new \invalid_parameter_exception('Invalid time value provided for availability.');
        }
        if ($availabilitytime > 0) { // Only set availability if time is a positive timestamp
            $availabilityinfo = [
                "op" => "&", "c" => [["type" => "date", "d" => ">=", "t" => $availabilitytime]],
                "showc" => [$cm->visible]
            ];
            $cm->availability = json_encode($availabilityinfo);
        }
    }
    // Note: $cm->idnumber, $cm->groupmode etc., are not set here, will take defaults.

    $cmid = add_course_module($cm); // This creates the cm record and returns the new cmid.
                                    // $cm->instance is now updated by add_course_module to 0 if not set.

    if (!$cmid) {
        throw new \moodle_exception('erroraddcoursemodule', 'webservice');
    }
    // $cm now has $cm->id = $cmid and $cm->instance might be 0.

    // --- Step 2: Prepare $instance object for page_add_instance, including the $cmid ---
    $instance = new \stdClass();
    $instance->course = $params['courseid']; // page table also stores courseid
    $instance->name = $params['pagename'];
    $instance->intro = '';
    $instance->introformat = \FORMAT_HTML;
    $instance->content = $params['pagecontent'];
    $instance->contentformat = \FORMAT_HTML;

    $instance->display = 5; // Value for PAGE_DISPLAY_INTERNAL
    $instance->displayoptions = serialize(array()); // Safe default
    $instance->printintro = 0; // Typical for page
    $instance->printlastmodified = 1; // Typical for page

    // CRITICAL ADDITION FOR THIS MOODLE VERSION:
    $instance->coursemodule = $cmid; // Pass the $cmid to page_add_instance

    // --- Step 3: Call page_add_instance ---
    // $mform is null as we are not using a Moodle form.
    $instanceid = page_add_instance($instance, null);

    if (!$instanceid) {
        // Rollback or cleanup: $cmid was created but page instance failed.
        // For simplicity now, just throw error. A more robust solution might delete the $cmid.
        course_delete_module($cmid); // Attempt to clean up the course_module record
        throw new \moodle_exception('erroraddpageinstance', 'mod_page');
    }
    // $instanceid is the new page.id.
    // page_add_instance internally should have already updated course_modules.instance
    // using $DB->set_field('course_modules', 'instance', $data->id, array('id'=>$cmid));

    // --- Step 4: Positioning (if needed) ---
    // add_course_module already added it to the section.
    // If $beforemod is used, this call is for specific positioning within the section.
    if (!is_null($params['beforemod'])) {
        course_add_cm_to_section($params['courseid'], $cmid, $params['sectionnum'], $params['beforemod']);
    }

    rebuild_course_cache($params['courseid']); // Good practice after adding modules.

    return [
        'message' => 'Successful',
        'id' => $cmid, // Return the course module ID
    ];
}

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_add_new_course_module_page_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_add_new_course_module_book_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value( PARAM_TEXT, 'id of course' ),
                'sectionnum' => new external_value( PARAM_TEXT, 'relative number of the section' ),
                'urlname' => new external_value( PARAM_TEXT, 'displayed mod name' ),
                'content' => new external_value( PARAM_TEXT, 'Content to insert' ),
                'time' => new external_value( PARAM_TEXT, 'defines the mod. visibility', VALUE_DEFAULT, null ),
                'visible' => new external_value( PARAM_TEXT, 'defines the mod. visibility' ),
                'beforemod' => new external_value( PARAM_TEXT, 'mod to set before', VALUE_DEFAULT, null ),
            )
        );
    }


    /**
     * Method to create a new course module containing a book.
     *
     * @param $courseid The course id.
     * @param $sectionnum The number of the section inside the course.
     * @param $urlname Displayname of the Module.
     * @param $content Content to publish.
     * @param $time availability time.
     * @param $visible visible for course members.
     * @param $beforemod Optional parameter, a Module where the new Module should be placed before.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_add_new_course_module_book($courseid, $sectionnum, $urlname, $content, $time = null, $visible, $beforemod = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/book' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/book' . '/locallib.php');

        debug("local_sync_service_add_new_course_module_book");

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_add_new_course_module_book_parameters(),
            array(
                'courseid' => $courseid,
                'sectionnum' => $sectionnum,
                'urlname' => $urlname,
                'content' => $content,
                'time' => $time,
                'visible' => $visible,
                'beforemod' => $beforemod,
            )
        );

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/book:addinstance', $context);


        $instance = new \stdClass();
        $instance->course = $params['courseid'];
        $instance->name = $params['urlname'];
        $instance->introformat = \FORMAT_HTML;
        $instance->completionexpected=null; //todo
        $instance->intro = '<p>'.$params['urlname'].'</p>';
        $instance->visible=1;
        $instance->id = book_add_instance($instance, null);

        debug("added book $instance->id");

        $modulename = 'book';
        $cm = new \stdClass();
        $cm->course     = $params['courseid'];
        $cm->instance   = $instance->id;
        $cm->module     = $DB->get_field( 'modules', 'id', array('name' => $modulename) );
        $cm->section    = $params['sectionnum'];
        if (!is_null($params['time'])) {
            $cm->availability = "{\"op\":\"&\",\"c\":[{\"type\":\"date\",\"d\":\">=\",\"t\":" . $params['time'] . "}],\"showc\":[" . $params['visible'] . "]}";
        } else if ( $params['visible'] === 'false' ) {
            $cm->visible = 0;
        }

        $cm->id = add_course_module( $cm );
        $cmid = $cm->id;
        debug("course module added $cmid\n");

        $secsectionid = course_add_cm_to_section($params['courseid'], $cmid, $params['sectionnum'], $params['beforemod']);

        debug("prepare add to section done $sectionid ");

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_add_new_course_module_book_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }
//

    /**
 * Defines parameters for adding a new label module.
 * @return external_function_parameters
 */
public static function local_sync_service_add_new_course_module_label_parameters() {
    return new external_function_parameters(
        array(
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number to add the label to'),
            'labelcontent' => new external_value(PARAM_RAW, 'HTML content of the label (this is what users see).'),
            'visible' => new external_value(PARAM_BOOL, 'Visibility of the label.'),
            'labelname' => new external_value(PARAM_TEXT, 'Internal name/identifier for the label (often not displayed).', VALUE_DEFAULT, ''), // Optional, can default or be derived
            'time' => new external_value(PARAM_INT, 'Availability time (Unix timestamp). Optional.', VALUE_DEFAULT, null), // Using VALUE_DEFAULT as it was less problematic
            'beforemod' => new external_value(PARAM_INT, 'Course module ID to place this label before. Optional.', VALUE_DEFAULT, null) // Using VALUE_DEFAULT
        )
    );
}

/**
 * Method to create a new course module containing a Label.
 *
 * @param int $courseid The course id.
 * @param int $sectionnum The number of the section inside the course.
 * @param string $labelname Optional internal name/identifier for the label.
 * @param string $labelcontent HTML content of the label.
 * @param bool $visible Visible for course members.
 * @param int|null $time Availability time.
 * @param int|null $beforemod Optional parameter, a Module where the new Module should be placed before.
 * @return array Message: Successful and $cmid of the new Module.
 */
public static function local_sync_service_add_new_course_module_label(
    $courseid, $sectionnum, $labelcontent, $visible,
    $labelname = '', $time = null, $beforemod = null
) {
    global $DB, $CFG;

    error_log("LABEL_DEBUG: Entered add_new_course_module_label function.");
    if (!empty($CFG->debugdeveloper)) { mtrace("LABEL_MTRACE: Entered add_new_course_module_label."); }

    require_once($CFG->dirroot . '/mod/label/lib.php'); // Changed to label/lib.php
    require_once($CFG->dirroot . '/course/lib.php');    // For add_course_module etc.

    // Parameter validation
    $params = self::validate_parameters(
        self::local_sync_service_add_new_course_module_label_parameters(),
        array(
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'labelcontent' => $labelcontent,
            'visible' => $visible,
            'labelname' => $labelname,
            'time' => $time,
            'beforemod' => $beforemod,
        )
    );

        error_log("LABEL_DEBUG: Parameters after validation: " . var_export($params, true));


    // Ensure the current user has required permission in this course.
    $context = context_course::instance($params['courseid']);
    self::validate_context($context);
    require_capability('mod/label:addinstance', $context); // Changed capability

    // --- Step 1: Create the course_modules record ---
    $modulename = 'label';
    $cm = new \stdClass();
    $cm->course = $params['courseid'];
    $cm->module = $DB->get_field('modules', 'id', array('name' => $modulename), MUST_EXIST);
    $cm->instance = 0; // Will be updated later by explicit override
    $cm->idnumber = ''; // Default idnumber
    //$cm->instance = $instanceid; // Use the ID from label_add_instance
    //$cm->section = $params['sectionnum'];

    // Determine actual section DB ID to pass to add_course_module (WORKAROUND)
    $target_section_number = $params['sectionnum'];
    $actual_section_db_id = $DB->get_field('course_sections', 'id', array('course' => $params['courseid'], 'section' => $target_section_number));

    if ($actual_section_db_id === false) {
        error_log("LABEL_DEBUG: Target section $target_section_number for course {$params['courseid']} does NOT exist. Passing section NUMBER to add_course_module to create it.");
        $cm->section = $target_section_number; // Let add_course_module create it and get the ID
    } else {
        error_log("LABEL_DEBUG: Target section $target_section_number for course {$params['courseid']} exists with DB ID $actual_section_db_id. Setting cm->section to this ID for add_course_module.");
        $cm->section = $actual_section_db_id; // Pass actual ID
    }

    $cm->visible = $params['visible'] ? 1 : 0;
    // Availability logic (same as before)
    if (!is_null($params['time'])) {
        $availabilitytime = (int)$params['time'];
        if ($availabilitytime < 0) { // Disallow negative
             throw new \invalid_parameter_exception('Invalid time value provided for availability.');
        }
        if ($availabilitytime > 0) {
            $showc_boolean = (bool)$cm->visible;
            $availabilityinfo = ["op"=>"&","c"=>[["type"=>"date","d"=>">=","t"=>$availabilitytime]],"showc"=>[$showc_boolean]];
            $cm->availability = json_encode($availabilityinfo);
        }
    }
        error_log("LABEL_DEBUG: CM object FINAL BEFORE add_course_module: " . var_export($cm, true));


    $cmid = add_course_module($cm);

    error_log("LABEL_DEBUG: AFTER add_course_module, returned cmid = " . var_export($cmid, true));

    $cm_db_record_after_add = $cmid ? $DB->get_record('course_modules', array('id' => $cmid)) : null;
    error_log("LABEL_DEBUG: DB mdl_course_modules for $cmid AFTER add_course_module: " . var_export($cm_db_record_after_add, true));

    if (!$cmid) {
        // If add_course_module fails, we might want to delete the label instance that was created.
        $DB->delete_records('label', array('id' => $instanceid));
        throw new \moodle_exception('erroraddcoursemodule', 'webservice', '', null, 'add_course_module returned invalid cmid for label.');
    }


    // --- Step 2: Prepare $instance object for label_add_instance ---
    $instance = new \stdClass();
    $instance->course = $params['courseid'];
    // For label, 'name' is less prominent. Can be short or derived.
    // If $params['labelname'] is empty, you could derive it from $params['labelcontent'] or set a default.
    $instance->name = !empty($params['labelname']) ? $params['labelname'] : substr(strip_tags($params['labelcontent']), 0, 50) . '...';
    if (empty($instance->name)) {
        $instance->name = get_string('pluginname', 'mod_label'); // Fallback name
    }

    $instance->intro = $params['labelcontent']; // The main content of a label is stored in 'intro'.
    $instance->introformat = FORMAT_HTML;       // Assuming HTML content.
    // Label table doesn't have display, displayoptions, printintro, printlastmodified like page.
    // It also doesn't typically need $instance->coursemodule passed in.
    $instance->timemodified = time();


    // Pass $cmid to label_add_instance via coursemodule property (WORKAROUND for your Moodle version)
    $instance->coursemodule = $cmid;
    $instance->idnumber = $cm->idnumber; // Carry over idnumber (even if empty)

    error_log("LABEL_DEBUG: Instance object BEFORE label_add_instance: " . var_export($instance, true));

    // --- Step 3: Call label_add_instance ---
    $instanceid = label_add_instance($instance, null); // $mform is null

        error_log("LABEL_DEBUG: AFTER label_add_instance, instanceid (label.id) = " . var_export($instanceid, true));


    if (!$instanceid) {
        course_delete_module($cmid); // Rollback CM record
        throw new \moodle_exception('erroraddlabelinstance', 'mod_label', '', null, 'label_add_instance returned invalid instance id.');
    }

// --- EXPLICIT UPDATE OVERRIDE for course_modules.instance ---
    $current_cm_instance_val = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("LABEL_DEBUG: DB CHECK BEFORE EXPLICIT INSTANCE UPDATE: course_modules.instance for cmid $cmid = " . var_export($current_cm_instance_val, true));

    if (($current_cm_instance_val == 0 || is_null($current_cm_instance_val)) && $instanceid > 0) {
        error_log("LABEL_DEBUG: course_modules.instance is 0/NULL for cmid $cmid. Attempting EXPLICIT update to instance $instanceid.");
        if ($DB->set_field('course_modules', 'instance', $instanceid, array('id' => $cmid))) {
            error_log("LABEL_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid to $instanceid SUCCEEDED.");
        } else {
            error_log("LABEL_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid FAILED. Rolling back.");
            course_delete_module($cmid);
            $DB->delete_records('label', array('id' => $instanceid)); // Delete label instance
            throw new \moodle_exception('errorupdatelinkcmlabel', 'local_sync_service', '', null, "Failed to link label instance $instanceid to CM $cmid.");
        }
    } else if ($instanceid <= 0) {
         error_log("LABEL_DEBUG: Not attempting explicit instance update because instanceid from label_add_instance is invalid: " . var_export($instanceid, true));
    } else {
        error_log("LABEL_DEBUG: course_modules.instance for cmid $cmid was already non-zero: " . var_export($current_cm_instance_val, true) . ". No explicit instance update needed.");
    }
    $final_cm_instance_value = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("LABEL_DEBUG: DB CHECK FINAL for instance: course_modules.instance for cmid $cmid = " . var_export($final_cm_instance_value, true));
    

// --- MANUAL SEQUENCE UPDATE (WORKAROUND) ---
    $final_section_db_id_for_sequence = $DB->get_field('course_modules', 'section', array('id' => $cmid));
    if ($final_section_db_id_for_sequence !== false) {
        $section_obj = $DB->get_record('course_sections', array('id' => $final_section_db_id_for_sequence, 'course' => $params['courseid']));
        if ($section_obj) {
            $current_sequence_array = array_filter(explode(',', $section_obj->sequence));
            $cmid_is_in_sequence = false;
            foreach ($current_sequence_array as $seq_cmid) {
                if ((string)$seq_cmid == (string)$cmid) { $cmid_is_in_sequence = true; break; }
            }
            if (!$cmid_is_in_sequence) {
                error_log("LABEL_DEBUG: cmid $cmid NOT found in section (ID: $final_section_db_id_for_sequence) sequence '{$section_obj->sequence}'. Attempting to add it.");
                $current_sequence_array[] = (string)$cmid;
                $section_obj->sequence = implode(',', array_unique(array_filter($current_sequence_array)));
                if ($DB->update_record('course_sections', $section_obj)) {
                    error_log("LABEL_DEBUG: MANUAL update of section sequence SUCCEEDED. New sequence: " . $section_obj->sequence);
                } else {
                    error_log("LABEL_DEBUG: MANUAL update of section sequence FAILED for section ID $final_section_db_id_for_sequence.");
                }
            } else {
                error_log("LABEL_DEBUG: cmid $cmid was already in section (ID: $final_section_db_id_for_sequence) sequence '{$section_obj->sequence}'.");
            }
        } else {
            error_log("LABEL_DEBUG: Could not find course_sections record with ID $final_section_db_id_for_sequence (from cmid $cmid's section field) for course {$params['courseid']} to update sequence.");
        }
    } else {
        error_log("LABEL_DEBUG: Could not retrieve final section ID from course_module record for cmid $cmid. Cannot update sequence.");
    }

    rebuild_course_cache($params['courseid']);

    return [
        'message' => 'Label added successfully.',
        'id' => $cmid,
    ];
}

/**
 * Describes the return value of the add_new_course_module_label function.
 * @return external_single_structure
 */
public static function local_sync_service_add_new_course_module_label_returns() {
    return new external_single_structure(
        array(
            'message' => new external_value(PARAM_TEXT, 'If the execution was successful.'),
            'id' => new external_value(PARAM_INT, 'CMID of the new label module.') // Changed to PARAM_INT
        )
    );
}





/**
 * Defines parameters for adding a new forum module.
 * @return external_function_parameters
 */
public static function local_sync_service_add_new_course_module_forum_parameters() {
    return new external_function_parameters(
        array(
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number to add the forum to'),
            'forumname' => new external_value(PARAM_TEXT, 'Name of the forum'),
            'visible' => new external_value(PARAM_BOOL, 'Visibility of the forum.'),
            'intro' => new external_value(PARAM_RAW, 'Introduction/description for the forum.', VALUE_DEFAULT, ''), // Changed
            'introformat' => new external_value(PARAM_INT, 'Format of the intro (e.g., FORMAT_HTML).', VALUE_DEFAULT, FORMAT_HTML), // Changed
            'forumtype' => new external_value(PARAM_TEXT, "Type of forum...", VALUE_DEFAULT, 'general'), // Changed
            'forcesubscribe' => new external_value(PARAM_INT, 'Subscription mode...', VALUE_DEFAULT, 0), // Changed
            'trackingtype' => new external_value(PARAM_INT, 'Read tracking...', VALUE_DEFAULT, 0), // Changed
            'maxbytes' => new external_value(PARAM_INT, 'Max attachment size...', VALUE_DEFAULT, 0), // Changed
            'maxattachments' => new external_value(PARAM_INT, 'Max number of attachments...', VALUE_DEFAULT, 1), // Changed
            'time' => new external_value(PARAM_INT, 'Availability time...', VALUE_DEFAULT, null), // Already VALUE_DEFAULT
            'beforemod' => new external_value(PARAM_INT, 'Course module ID...', VALUE_DEFAULT, null) // Already VALUE_DEFAULT
        )
    );
}

/**
 * Method to create a new course module containing a Forum.
 *
 * @param int $courseid
 * @param int $sectionnum
 * @param string $forumname
 * @param string $intro
 * @param int $introformat
 * @param string $forumtype
 * @param int $forcesubscribe
 * @param int $trackingtype
 * @param int $maxbytes
 * @param int $maxattachments
 * @param bool $visible
 * @param int|null $time
 * @param int|null $beforemod
 * @return array Message and CMID.
 */
public static function local_sync_service_add_new_course_module_forum(
    $courseid, $sectionnum, $forumname, $visible,
    $intro = '', $introformat = FORMAT_HTML, $forumtype = 'general',
    $forcesubscribe = 0, $trackingtype = 0, $maxbytes = 0, $maxattachments = 1,
    $time = null, $beforemod = null
) {
    global $DB, $CFG;

    // TEST LOGGING
    error_log("SYNC_SERVICE_DEBUG: Entered add_new_course_module_forum at " . time());
    if (!empty($CFG->debugdeveloper)) { // Use $CFG->debugdeveloper
        mtrace("SYNC_SERVICE_MTRACE: Entered add_new_course_module_forum with Moodle Debugging ON.");
    } else {
        error_log("SYNC_SERVICE_DEBUG: Moodle Developer Debugging is OFF (\$CFG->debugdeveloper is not set/empty).");
    }
    // END TEST LOGGING

    require_once($CFG->dirroot . '/mod/forum/lib.php');
    require_once($CFG->dirroot . '/course/lib.php');

    // Parameter validation
    $params = self::validate_parameters(
        self::local_sync_service_add_new_course_module_forum_parameters(),
        array(
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'forumname' => $forumname,
            'visible' => $visible,
            'intro' => $intro,
            'introformat' => $introformat,
            'forumtype' => $forumtype,
            'forcesubscribe' => $forcesubscribe,
            'trackingtype' => $trackingtype,
            'maxbytes' => $maxbytes,
            'maxattachments' => $maxattachments,
            'time' => $time,
            'beforemod' => $beforemod,
        )

    );

        error_log("FORUM_DEBUG: Parameters after validation: " . var_export($params, true));


    // Context and capability
    $context = context_course::instance($params['courseid']);
    self::validate_context($context);
    require_capability('mod/forum:addinstance', $context);

    // --- Step 1: Create the course_modules record first to get a $cmid ---
    $modulename = 'forum';
    $cm = new \stdClass();
    $cm->course = $params['courseid'];
    $cm->module = $DB->get_field('modules', 'id', array('name' => $modulename), MUST_EXIST);
    $cm->instance = 0; // Explicitly set to 0 before add_course_module
    $cm->idnumber = ''; // <<<< ADD THIS

// === REVISED LOGIC for section ID ===
$target_section_number = $params['sectionnum']; // e.g., 0
$actual_section_db_id = $DB->get_field('course_sections', 'id', array('course' => $params['courseid'], 'section' => $target_section_number));

if ($actual_section_db_id === false) {
    // This case means the section (by number) does not exist yet.
    // add_course_module is supposed to create it if it gets a section *number*.
    // This is a more complex scenario: if the section doesn't exist, add_course_module creates it,
    // and *then* its ID would be correct. For now, let's assume sections usually exist or section 0 always does.
    // If this happens, you might need to let add_course_module try with the number, then query the section ID again.
    // For simplicity, if section 0 *must* exist (and has ID 167):
    error_log("FORUM_DEBUG: CRITICAL - Could not find course_sections record for course {$params['courseid']} and section number $target_section_number. This should not happen for section 0 if it exists. Cannot proceed reliably.");
    // You might throw an exception here or default to a known good section ID if that makes sense.
    // For now, to ensure we pass an ID if found:
    // This path implies an issue with $params['sectionnum'] not matching an existing section.
    // Let's re-evaluate. If section 0 always has ID 167, this 'false' case shouldn't happen for section 0.
    // If a higher section number is passed that doesn't exist, add_course_module *should* create it.
    // The most robust way is to let add_course_module handle section creation if it doesn't exist.
    // So, we pass the section NUMBER to add_course_module, and then we must FIX the record if add_course_module stored the number instead of the ID.

    // Let's revert to the plan of fixing it *after* add_course_module if it's wrong.
    // So, this block before add_course_module simply ensures $cm->section has the section NUMBER.
    $cm->section = $target_section_number;
    error_log("FORUM_DEBUG: Setting cm->section to section NUMBER: $target_section_number for add_course_module, as per API contract.");

} else {
    // Section number exists, and we know its actual DB ID ($actual_section_db_id, e.g., 167).
    // The API contract for add_course_module is to pass the section NUMBER.
    // However, since we know our add_course_module version is problematic and inserts
    // the number directly if it's '0', we should preemptively set the correct ID.
    error_log("FORUM_DEBUG: Target section number $target_section_number for course {$params['courseid']} maps to course_sections.id $actual_section_db_id. Setting cm->section to ACTUAL DB ID: $actual_section_db_id before calling add_course_module.");
    $cm->section = $actual_section_db_id; // <<<< SET THE ACTUAL DB ID HERE
}
// === END REVISED LOGIC ===


    $cm->visible = $params['visible'] ? 1 : 0;
    // $cm->instance will be 0 or not set initially.

    // Availability logic
    if (!is_null($params['time'])) {
        $availabilitytime = (int)$params['time'];
        if ($availabilitytime < 0) {
             throw new \invalid_parameter_exception('Invalid time value provided for availability.');
        }
        // Inside the if (!is_null($params['time'])) block:
        if ($availabilitytime > 0) { // Only set JSON availability if time is a positive timestamp
            $showc_boolean = (bool)$cm->visible; // Correct: $cm->visible is 0 or 1, so this becomes true or false
            $availabilityinfo = [
                "op" => "&", // AND operator
                "c" => [ // Conditions
                    [
                        "type" => "date",
                        "d" => ">=", // "is after or equal to"
                        "t" => $availabilitytime
                    ]
                ],
                "showc" => [$showc_boolean] // <<<< CORRECTED: Use the boolean variable here
            ];
            $cm->availability = json_encode($availabilityinfo);
        }
    }

        error_log("FORUM_DEBUG: CM object BEFORE add_course_module: " . var_export($cm, true));


    $cmid = add_course_module($cm); // Create CM entry, get $cmid. $cm->instance will be 0.



    error_log("FORUM_DEBUG: AFTER add_course_module, cmid = " . var_export($cmid, true) . ", cm->instance (should be 0) = " . var_export(isset($cm->instance) ? $cm->instance : 'not set', true));

    // Log the $cm->section *after* add_course_module to see if it changed it or what it used
    $cm_after_add = $DB->get_record('course_modules', array('id' => $cmid)); // Get the actual record
    if ($cm_after_add) {
        error_log("FORUM_DEBUG: DB course_modules.section for cmid $cmid is: " . var_export($cm_after_add->section, true));
    } else {
        error_log("FORUM_DEBUG: Could not fetch course_module record for cmid $cmid after creation.");
    }


    if (!$cmid) {
        throw new \moodle_exception('erroraddcoursemodule', 'webservice');
    }
    // $cm->id is now $cmid.

    // --- Step 2: Prepare $instance object for forum_add_instance, INCLUDING $cmid ---
    $instance = new \stdClass();
    $instance->course = $params['courseid']; // forum table also stores courseid
    $instance->name = $params['forumname'];
    $instance->intro = $params['intro'];
    $instance->introformat = $params['introformat'];
    $instance->type = $params['forumtype'];
    $instance->forcesubscribe = $params['forcesubscribe'];
    $instance->trackingtype = $params['trackingtype'];
    $instance->maxbytes = $params['maxbytes'];
    $instance->maxattachments = $params['maxattachments'];
    // Add other forum-specific instance fields if needed based on $params
$instance->completionexpected = 0; // Or null. ADD THIS.

    // Add grading related defaults to avoid warnings
$instance->assessed = 0; // Default: Forum is not graded (0 = no grading, 1 = points, 2 = scale)
                         // Note: forum uses 'scale' for points if assessed=0 but scale > 0, or scale < 0 for actual scale id.
                         // Simpler is assessed = 0 (not graded) or assessed = points value.
                         // forum_add_instance might have its own default logic if 'assessed' is not present.
                         // Let's explicitly set it.
$instance->scale = 0;    // Default: Max grade (if points) or scale ID (if using a scale).
                         // If $instance->assessed = 0, this $instance->scale might be what becomes $grade_forum if used directly.
                         // Or, the property Moodle expects might be $instance->grade (common for many modules).
                         // Let's try setting what seems to be expected from the warning.

// The warning was for $grade_forum. This property isn't standard on the $instance for add_instance.
// It's more likely that $instance->scale or $instance->grade is used internally and then
// something calculates or refers to $grade_forum.
// For now, let's ensure the common grading properties are set.
// $instance->grade = 0; // Another common property for max grade.
// `forum_add_instance` might be looking at $instance->scale. If $instance->assessed is 0,
// it often means "no grade". If $instance->assessed is some positive number, that's the max points.
// If $instance->assessed is negative, it's a scale ID.
// The property `grade_forum` from the warning is unusual as a direct property on the instance.
// It's more likely derived or part of an internal structure.

// Let's look at how mod/forum/mod_form.php defines grading:
// It uses 'scale' for points (if >0) or scale_id (if <0)
// and 'assessed' (0 or 1 for simple 'grade' type selection which then maps to scale)
// To be safe and align with typical Moodle module structure:
$instance->grade = 0; // Corresponds to max points, 0 if not graded via points.
                      // `forum_add_instance` will likely use `assessed` and `scale` primarily.
                      // The `grade_forum` warning might be a red herring if `assessed` and `scale` are set correctly.

// Let's ensure all properties that forum_add_instance might use for grading are present,
// even if we default them to "not graded".
$instance->assessed = 0; // 0 = No ratings, >0 = Aggregate type (COUNT, MAX, etc.) for ratings
$instance->assesstimestart = 0;
$instance->assesstimeend = 0;
$instance->scale = 0; // If assesed > 0, this is the scale id (negative) or points (positive)

// The warning `Undefined property: stdClass::$grade_forum` suggests that somewhere in forum's lib,
// it's directly trying to access `$data->grade_forum`. This is not typical for the initial $data object.
// This property might be something set *internally* within forum_add_instance or a helper it calls.
// However, the warning implies it's trying to *read* it from the object *you provide*.

// What if we try to set it based on what it might expect for "whole forum grading" (legacy)?
// This is a guess:
// $instance->grade_forum = 0; // If not using whole forum grading.
// This is less likely, usually these are derived from `assessed` and `scale`.

// Let's try setting what mod_form.php sets:
// $instance->ratingtime // (boolean for enabling rating period) - probably not needed if not rating
// $instance->rssarticles
// $instance->rsstype

// Back to basics for the `$instance` object before `forum_add_instance`:
// Ensure you have all parameters from your `_parameters` f

$instance->idnumber = ''; // Or copy from $cm->idnumber if you had a specific one


    // CRITICAL CHANGE FOR THIS MOODLE VERSION'S forum_add_instance:
    $instance->coursemodule = $cmid; // Pass the $cmid



// So, let's try adding these directly to the $instance object passed to forum_add_instance:
$instance->cmidnumber = ''; // Add this to $instance
// For grade_forum, it's tricky. What value would it expect?
// If the forum is NOT graded as a whole, it should probably be 0 or not matter.
// Check forum_add_instance: does it set a default if this is missing? The warning says no.
$instance->grade_forum = 0; // Add this to $instance, assuming 0 means not graded / default

    error_log("FORUM_DEBUG: Instance object BEFORE forum_add_instance: " . var_export($instance, true));


    // --- Step 3: Call forum_add_instance ---
        $previous_error_reporting = error_reporting(E_ALL); // Capture all errors

    $instanceid = forum_add_instance($instance, null); // $mform is null

    error_reporting($previous_error_reporting); // Restore previous error reporting
    //ini_set('display_errors', '0'); // Turn back off if it was off

    error_log("FORUM_DEBUG: AFTER forum_add_instance, instanceid (forum.id) = " . var_export($instanceid, true));
    error_log("FORUM_DEBUG: AFTER forum_add_instance, instance object's ID property (instance->id) = " . var_export(isset($instance->id) ? $instance->id : 'not set', true));


    if (!$instanceid) { // If forum_add_instance truly fails to create the forum record (returns 0, false, null)
        error_log("FORUM_DEBUG: forum_add_instance returned a falsy value for instanceid: " . var_export($instanceid, true) . ". Rolling back cmid $cmid.");
        course_delete_module($cmid);
        throw new \moodle_exception('erroraddforuminstance', 'mod_forum', '', null, 'forum_add_instance did not return a valid instance ID.');
    }

// --- EXPLICIT UPDATE OVERRIDE for course_modules.instance ---
    $current_cm_instance_val = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("FORUM_DEBUG: DB CHECK BEFORE EXPLICIT UPDATE: course_modules.instance for cmid $cmid = " . var_export($current_cm_instance_val, true));

    if (($current_cm_instance_val == 0 || is_null($current_cm_instance_val)) && $instanceid > 0) {
        error_log("FORUM_DEBUG: course_modules.instance is 0 or NULL for cmid $cmid. Attempting EXPLICIT update to instance $instanceid.");
        if ($DB->set_field('course_modules', 'instance', $instanceid, array('id' => $cmid))) {
            error_log("FORUM_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid to $instanceid SUCCEEDED.");
        } else {
            error_log("FORUM_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid to $instanceid FAILED. This is very problematic. The forum will not be linked.");
            // At this point, the forum instance exists, the cm record exists, but they are not linked.
            // This is a critical failure. You might consider a more drastic error or cleanup.
            // For now, let's log it and allow the function to complete, but it won't work correctly.
            // A more robust solution might throw an exception here.
             course_delete_module($cmid); // Delete the cm record
             $DB->delete_records('forum', array('id' => $instanceid)); // Delete the forum instance
             throw new \moodle_exception('errorupdatelinkcmforum', 'local_sync_service', '', null, "Failed to link forum instance $instanceid to course module $cmid.");
        }
    } else if ($instanceid <= 0) {
         error_log("FORUM_DEBUG: Not attempting explicit update because instanceid from forum_add_instance is invalid: " . var_export($instanceid, true));
    } else {
        error_log("FORUM_DEBUG: course_modules.instance for cmid $cmid was already non-zero: " . var_export($current_cm_instance_val, true) . ". No explicit update needed.");
    }


    // --- Check DB for update ---
    $final_cm_instance_value = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("FORUM_DEBUG: DB CHECK FINAL: course_modules.instance for cmid $cmid = " . var_export($final_cm_instance_value, true));

// If after all that, it's still not linked, something is seriously wrong.
    if ($final_cm_instance_value != $instanceid || $final_cm_instance_value == 0) {
        // This check is a bit redundant if the explicit update throws an exception on failure,
        // but good for a final sanity check if you make the explicit update non-fatal.
        error_log("FORUM_DEBUG: FINAL CRITICAL CHECK - Forum $instanceid not correctly linked to cmid $cmid. Instance value in DB is $final_cm_instance_value.");
        // Consider throwing an exception if you haven't already in the explicit update failure block.
    }



    // $instanceid is the new forum.id.
    // forum_add_instance (in your Moodle version) uses the passed $cmid to update
    // the course_modules table's 'instance' field and to get context.

    // --- Step 4: Positioning (if needed) ---
    if (!is_null($params['beforemod']) && (int)$params['beforemod'] > 0) {
        if ($DB->record_exists('course_modules', ['id' => $params['beforemod'], 'course' => $params['courseid']])) {
            course_add_cm_to_section($params['courseid'], $cmid, $params['sectionnum'], $params['beforemod']);
        } 
    }


/* temp Swap    // --- MANUAL SEQUENCE UPDATE (WORKAROUND) ---
    $section_db_id = $DB->get_field('course_modules', 'section', array('id' => $cmid));
    if ($section_db_id !== false) { // Check if we got a valid section ID from the cm record
        $section_obj = $DB->get_record('course_sections', array('id' => $section_db_id, 'course' => $params['courseid']));
        if ($section_obj) {
        */ 

// new version to fix section id referencing 
    $final_section_db_id_for_sequence = $DB->get_field('course_modules', 'section', array('id' => $cmid));
    // This $final_section_db_id_for_sequence should now be the correct one (e.g., 167)

    if ($final_section_db_id_for_sequence !== false) {
        $section_obj = $DB->get_record('course_sections', array('id' => $final_section_db_id_for_sequence, 'course' => $params['courseid']));
        if ($section_obj) {

            //swap back, if needed, with above.

            $current_sequence_array = array_filter(explode(',', $section_obj->sequence)); // Get existing, filter empty
            
            // Check if cmid is already in sequence (as string or int, to be safe)
            $cmid_is_in_sequence = false;
            foreach ($current_sequence_array as $seq_cmid) {
                if ((string)$seq_cmid == (string)$cmid) {
                    $cmid_is_in_sequence = true;
                    break;
                }
            }

            if (!$cmid_is_in_sequence) {
                error_log("FORUM_DEBUG: cmid $cmid NOT found in section (ID: $section_db_id) sequence '{$section_obj->sequence}'. Attempting to add it.");
                $current_sequence_array[] = (string)$cmid; // Add as string
                $section_obj->sequence = implode(',', array_unique(array_filter($current_sequence_array))); // Ensure unique and filter empties again
                
                if ($DB->update_record('course_sections', $section_obj)) {
                    error_log("FORUM_DEBUG: MANUAL update of section sequence SUCCEEDED. New sequence: " . $section_obj->sequence);
                } else {
                    error_log("FORUM_DEBUG: MANUAL update of section sequence FAILED for section ID $section_db_id.");
                    // This is problematic. The module won't appear.
                    // Consider if an exception should be thrown here.
                }
            } else {
                error_log("FORUM_DEBUG: cmid $cmid was already in section (ID: $section_db_id) sequence '{$section_obj->sequence}'. No manual sequence update needed.");
            }
        } else {
            error_log("FORUM_DEBUG: Could not find course_sections record with ID $section_db_id (from cmid $cmid) for course {$params['courseid']} to update sequence.");
        }
    } else {
        error_log("FORUM_DEBUG: Could not retrieve section ID from course_module record for cmid $cmid. Cannot update sequence.");
    }



    rebuild_course_cache($params['courseid']);

    return [
        'message' => 'Forum added successfully.',
        'id' => $cmid,
    ];
}


/**
 * Describes the return value of the add_new_course_module_forum function.
 * @return external_single_structure
 */
public static function local_sync_service_add_new_course_module_forum_returns() {
    return new external_single_structure(
        array(
            'message' => new external_value(PARAM_TEXT, 'If the execution was successful.'),
            'id' => new external_value(PARAM_INT, 'CMID of the new forum module.')
        )
    );
}


/**
 * Defines parameters for adding a new assignment module.
 * @return external_function_parameters
 */
public static function local_sync_service_add_new_course_module_assignment_parameters() {
    return new external_function_parameters(
        array(
            // Order to match PHP function signature
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'assignmentname' => new external_value(PARAM_TEXT, 'Name of the assignment'),
            'intro' => new external_value(PARAM_RAW, 'Assignment description/instructions.', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visibility of the assignment.'),

            // Availability Dates (all optional, timestamps)
            'allowsubmissionsfromdate' => new external_value(PARAM_INT, 'Allow submissions from (timestamp). Optional.', VALUE_DEFAULT, 0),
            'duedate' => new external_value(PARAM_INT, 'Due date (timestamp). Optional.', VALUE_DEFAULT, 0),
            'cutoffdate' => new external_value(PARAM_INT, 'Cut-off date (timestamp). Optional.', VALUE_DEFAULT, 0),
            'alwaysshowdescription' => new external_value(PARAM_BOOL, 'Show description before open date. Default false.', VALUE_DEFAULT, false),

            // Submission Types
            'submission_file_enabled' => new external_value(PARAM_BOOL, 'Enable file submissions. Default true.', VALUE_DEFAULT, true),
            'submission_onlinetext_enabled' => new external_value(PARAM_BOOL, 'Enable online text submissions. Default false.', VALUE_DEFAULT, false),
            'submission_file_maxfiles' => new external_value(PARAM_INT, 'Max files for file submission. Default 1.', VALUE_DEFAULT, 1),
            // maxsizebytes can default to course/site limit or be specified

            // Grading
            'grade' => new external_value(PARAM_INT, 'Maximum grade/points. Default 100.', VALUE_DEFAULT, 100),

            // Optional generic parameters
            'introformat' => new external_value(PARAM_INT, 'Format of the intro.', VALUE_DEFAULT, FORMAT_HTML),
            'time' => new external_value(PARAM_INT, 'Overall module availability time (Unix timestamp). Optional.', VALUE_DEFAULT, null), // For $cm->availability
            'beforemod' => new external_value(PARAM_INT, 'CMID to place before. Optional.', VALUE_DEFAULT, null)
        )
    );
}

/**
 * Describes the return value of the add_new_course_module_assignment function.
 * @return external_single_structure
 */
public static function local_sync_service_add_new_course_module_assignment_returns() {
    return new external_single_structure(
        array(
            'message' => new external_value(PARAM_TEXT, 'If the execution was successful.'),
            'id' => new external_value(PARAM_INT, 'CMID of the new assignment module.')
        )
    );
}

/**
 * Method to create a new course module containing an Assignment.
 */
public static function local_sync_service_add_new_course_module_assignment(
    // Required parameters (match _parameters order)
    $courseid, $sectionnum, $assignmentname, $intro, $visible,
    // Optional parameters (match _parameters order and PHP defaults)
    $allowsubmissionsfromdate = 0, $duedate = 0, $cutoffdate = 0, $alwaysshowdescription = false,
    $submission_file_enabled = true, $submission_onlinetext_enabled = false, $submission_file_maxfiles = 1,
    $grade = 100,
    $introformat = FORMAT_HTML, $time = null, $beforemod = null
) {
    global $DB, $CFG;

    error_log("ASSIGN_DEBUG: Entered add_new_course_module_assignment function.");
    // if (!empty($CFG->debugdeveloper)) { mtrace("ASSIGN_MTRACE: Entered."); } // Optional

    require_once($CFG->dirroot . '/mod/assign/locallib.php'); // Often better to include locallib for assignments
    require_once($CFG->dirroot . '/mod/assign/lib.php');
    require_once($CFG->dirroot . '/course/lib.php');

    // Parameter validation
    $params = self::validate_parameters(
        self::local_sync_service_add_new_course_module_assignment_parameters(),
        array( // Ensure this array's keys map to _parameters and values match PHP arg order
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'assignmentname' => $assignmentname,
            'intro' => $intro,
            'visible' => $visible,
            'allowsubmissionsfromdate' => $allowsubmissionsfromdate,
            'duedate' => $duedate,
            'cutoffdate' => $cutoffdate,
            'alwaysshowdescription' => $alwaysshowdescription,
            'submission_file_enabled' => $submission_file_enabled,
            'submission_onlinetext_enabled' => $submission_onlinetext_enabled,
            'submission_file_maxfiles' => $submission_file_maxfiles,
            'grade' => $grade,
            'introformat' => $introformat,
            'time' => $time,
            'beforemod' => $beforemod,
        )
    );
    error_log("ASSIGN_DEBUG: Parameters after validation: " . var_export($params, true));

    // Context and capability
    $context = context_course::instance($params['courseid']);
    self::validate_context($context);
    require_capability('mod/assign:addinstance', $context);

    // --- Step 1: Prepare CM object & Call add_course_module first ---
    $modulename = 'assign';
    $cm = new \stdClass();
    $cm->course = $params['courseid'];
    $cm->module = $DB->get_field('modules', 'id', array('name' => $modulename), MUST_EXIST);
    $cm->instance = 0;
    $cm->idnumber = '';

    $target_section_number = $params['sectionnum'];
    $actual_section_db_id = $DB->get_field('course_sections', 'id', array('course' => $params['courseid'], 'section' => $target_section_number));
    if ($actual_section_db_id === false) {
        error_log("ASSIGN_DEBUG: Target section $target_section_number for course {$params['courseid']} does NOT exist. Passing section NUMBER to add_course_module.");
        $cm->section = $target_section_number;
    } else {
        error_log("ASSIGN_DEBUG: Target section $target_section_number for course {$params['courseid']} exists with DB ID $actual_section_db_id. Setting cm->section to this ID.");
        $cm->section = $actual_section_db_id;
    }
    $cm->visible = $params['visible'] ? 1 : 0;

    if (!is_null($params['time'])) { /* ... availability logic as before ... */
        $availabilitytime = (int)$params['time'];
        if ($availabilitytime < 0) { throw new \invalid_parameter_exception('Invalid time for availability.'); }
        if ($availabilitytime > 0) {
            $showc_boolean = (bool)$cm->visible;
            $availabilityinfo = ["op"=>"&","c"=>[["type"=>"date","d"=>">=","t"=>$availabilitytime]],"showc"=>[$showc_boolean]];
            $cm->availability = json_encode($availabilityinfo);
        }
    }

    error_log("ASSIGN_DEBUG: CM object FINAL BEFORE add_course_module: " . var_export($cm, true));
    $cmid = add_course_module($cm);
    error_log("ASSIGN_DEBUG: AFTER add_course_module, returned cmid = " . var_export($cmid, true));
    // ... (log $cm_db_record_after_add as before) ...

    if (!$cmid) { throw new \moodle_exception('erroraddcoursemodule', 'webservice'); }

    // --- Step 2: Prepare $instance object for assign_add_instance ---
    $instance = new \stdClass();
    $instance->course = $params['courseid'];
    $instance->name = $params['assignmentname'];
    $instance->intro = $params['intro'];
    $instance->introformat = $params['introformat'];

    $instance->allowsubmissionsfromdate = (int)$params['allowsubmissionsfromdate'];
    $instance->duedate = (int)$params['duedate'];
    $instance->cutoffdate = (int)$params['cutoffdate'];
    $instance->alwaysshowdescription = $params['alwaysshowdescription'] ? 1 : 0;

    // Submission plugin settings (directly on $instance object for assign_add_instance)
    $instance->assignsubmission_file_enabled = $params['submission_file_enabled'] ? 1 : 0;
    $instance->assignsubmission_onlinetext_enabled = $params['submission_onlinetext_enabled'] ? 1 : 0;
    $instance->assignsubmission_file_maxfiles = (int)$params['submission_file_maxfiles'];
    // Default other submission settings if not passed (assign_add_instance has defaults)
    // e.g., $instance->assignsubmission_file_maxsizebytes = 0; (for course/site default)

    // Grading settings
    $instance->grade = (int)$params['grade']; // Max points
    // assign_add_instance sets many grading defaults (e.g., gradepoint, gradingmethod 'simpledirect', etc.)
    // Add more here if you want to control them via API:
    // $instance->gradingmethod = 'simpledirect';
    // $instance->blindmarking = 0;
    // $instance->attemptreopenmethod = ASSIGN_ATTEMPT_REOPEN_METHOD_NONE; (constant from assign/lib.php)
    // $instance->maxattempts = -1; // Unlimited

    // Defaults for properties that might cause warnings if missing (like in Forum)
    $instance->idnumber = $cm->idnumber; // Carry over
    $instance->coursemodule = $cmid;    // CRITICAL for your Moodle version
    $instance->completionexpected = 0;  // Default

    // ADDED TO FIX "submissiondrafts cannot be null"
    $instance->submissiondrafts = 1; // Default to allowing drafts. Change to 0 if you prefer.
    $instance->requiresubmissionstatement = 0; // Default to No. Change to 1 if you prefer.

// Proactive additions for other potential NOT NULL columns:
    $instance->sendnotifications = 0;          // Default: No (Notify graders about submissions)
                                               // (Value from your INSERT for 'sendstudentnotifications' was '1', that's different)
    $instance->sendlatenotifications = 0;      // Default: No (Notify graders about late submissions)
    $instance->sendstudentnotifications = 1;   // Based on your previous INSERT (value 10 => '1')
                                               // Ensure this is either a parameter or a deliberate default.
                                               // If this isn't a parameter you control, assign_add_instance might set it.
                                               // If it's a parameter, make sure it's passed.
                                               // If it needs to be defaulted, 1 (Yes) is common.

    $instance->gradingduedate = 0;             // Default: Not enabled (timestamp)

    $instance->teamsubmission = 0;             // Default: No team submission
    $instance->requireallteammemberssubmit = 0;// Default: Not applicable if not team submission
    $instance->blindmarking = 0;               // Default: No
    $instance->markingworkflow = 0;            // Default: No

    // These are often defaulted by assign_add_instance itself if not provided,
    // but your version seems stricter.
    $instance->completionsubmit = 0;           // Default: 0 (submission does not mark as complete)
    $instance->markingallocation = 0;          // Default: 0 (no marking allocation)
    $instance->gradepenalty = 0;               // Default: 0 (no grade penalty)


    // Other defaults you might need (from your Forum/Page fixes for warnings)
    $instance->assessed = 0; // Assuming default no rating/advanced grading for assign itself
    $instance->scale = 0;    // If grade is used for points, scale is often 0
    $instance->timemodified = time();


    error_log("ASSIGN_DEBUG: Instance object BEFORE assign_add_instance: " . var_export($instance, true));

    // --- Step 3: Call assign_add_instance ---
    $instanceid = assign_add_instance($instance, null); // $mform is null
    error_log("ASSIGN_DEBUG: AFTER assign_add_instance, instanceid (assign.id) = " . var_export($instanceid, true));

    if (!$instanceid) {
        course_delete_module($cmid);
        throw new \moodle_exception('erroraddassigninstance', 'mod_assign');
    }

    // --- EXPLICIT UPDATE OVERRIDE for course_modules.instance ---
    $current_cm_instance_val = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("ASSIGN_DEBUG: DB CHECK BEFORE EXPLICIT INSTANCE UPDATE: course_modules.instance for cmid $cmid = " . var_export($current_cm_instance_val, true));
    if (($current_cm_instance_val == 0 || is_null($current_cm_instance_val)) && $instanceid > 0) {
        error_log("ASSIGN_DEBUG: course_modules.instance is 0/NULL for cmid $cmid. Attempting EXPLICIT update to instance $instanceid.");
        if ($DB->set_field('course_modules', 'instance', $instanceid, array('id' => $cmid))) {
            error_log("ASSIGN_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid to $instanceid SUCCEEDED.");
        } else {
            error_log("ASSIGN_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid FAILED. Rolling back.");
            course_delete_module($cmid); $DB->delete_records('assign', array('id' => $instanceid)); // Delete assign instance
            throw new \moodle_exception('errorupdatelinkcmassign', 'local_sync_service', '', null, "Failed to link assign instance $instanceid to CM $cmid.");
        }
    } // ... (else log if no update needed or $instanceid was bad) ...
    $final_cm_instance_value = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("ASSIGN_DEBUG: DB CHECK FINAL for instance: course_modules.instance for cmid $cmid = " . var_export($final_cm_instance_value, true));


// --- MANUAL SEQUENCE UPDATE (WORKAROUND) ---
    $final_section_db_id_for_sequence = $DB->get_field('course_modules', 'section', array('id' => $cmid));
    if ($final_section_db_id_for_sequence !== false) {
        $section_obj = $DB->get_record('course_sections', array('id' => $final_section_db_id_for_sequence, 'course' => $params['courseid']));
        if ($section_obj) {
            // ... (your full sequence update logic from Forum/Label) ...
            $current_sequence_array = array_filter(explode(',', $section_obj->sequence));
            $cmid_is_in_sequence = in_array((string)$cmid, $current_sequence_array);
            if (!$cmid_is_in_sequence) {
                error_log("ASSIGN_DEBUG: cmid $cmid NOT found in section (ID: $final_section_db_id_for_sequence) sequence '{$section_obj->sequence}'. Attempting manual add.");
                $current_sequence_array[] = (string)$cmid;
                $section_obj->sequence = implode(',', array_unique(array_filter($current_sequence_array)));
                if (!$DB->update_record('course_sections', $section_obj)) {
                    error_log("ASSIGN_DEBUG: MANUAL update of section sequence FAILED for section ID $final_section_db_id_for_sequence.");
                } else {
                     error_log("ASSIGN_DEBUG: MANUAL update of section sequence SUCCEEDED. New sequence: " . $section_obj->sequence);
                }
            }
        } else { error_log("ASSIGN_DEBUG: MANUAL update of section sequence FAILED for section ID $final_section_db_id_for_sequence."); }
    } else { error_log("ASSIGN_DEBUG: MANUAL update of section sequence FAILED for section ID $final_section_db_id_for_sequence."); }


    rebuild_course_cache($params['courseid']);

    return [
        'message' => 'Assignment added successfully.',
        'id' => $cmid,
    ];
}


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_import_html_in_book_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'course module id of book' ),
                'itemid' => new external_value( PARAM_TEXT, 'itemid containing preloaded zip file to import in book' ),
                'type' => new external_value( PARAM_TEXT, 'type (typezipdirs or typezipfiles)' )
            )
        );
    }


/**
 * Defines parameters for adding a new quiz module.
 * @return external_function_parameters
 */
public static function local_sync_service_add_new_course_module_quiz_parameters() {
    // Define QUIZ_GRADEHIGHEST if not already available, or use its integer value.
    // It's typically 1. Check your mod/quiz/lib.php
    if (!defined('QUIZ_GRADEHIGHEST')) { define('QUIZ_GRADEHIGHEST', 1); }

    return new external_function_parameters(
        array(
            // Order to match PHP function signature
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'quizname' => new external_value(PARAM_TEXT, 'Name of the quiz'),
            'intro' => new external_value(PARAM_RAW, 'Quiz introduction.', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visibility of the quiz.'),

            // Timing
            'timeopen' => new external_value(PARAM_INT, 'Quiz open time (timestamp). Default 0 (disabled).', VALUE_DEFAULT, 0),
            'timeclose' => new external_value(PARAM_INT, 'Quiz close time (timestamp). Default 0 (disabled).', VALUE_DEFAULT, 0),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds. Default 0 (disabled).', VALUE_DEFAULT, 0),

            // Grading
            'grade' => new external_value(PARAM_NUMBER, 'Maximum grade for the quiz. Default 10.0.', VALUE_DEFAULT, 10.0), // PARAM_NUMBER for float
            'attempts' => new external_value(PARAM_INT, 'Attempts allowed (0 for unlimited). Default 0.', VALUE_DEFAULT, 0),
            'grademethod' => new external_value(PARAM_INT, 'Grading method (e.g., 1 for Highest Grade). Default QUIZ_GRADEHIGHEST.', VALUE_DEFAULT, QUIZ_GRADEHIGHEST),

            // Layout
            'questionsperpage' => new external_value(PARAM_INT, 'Questions per page. Default 0 (all on one page).', VALUE_DEFAULT, 0),

            // Optional generic parameters
            'introformat' => new external_value(PARAM_INT, 'Format of the intro.', VALUE_DEFAULT, FORMAT_HTML),
            'moduleavailabilitytime' => new external_value(PARAM_INT, 'Overall module availability time (Unix timestamp). Optional.', VALUE_DEFAULT, null), // For $cm->availability
            'beforemod' => new external_value(PARAM_INT, 'CMID to place before. Optional.', VALUE_DEFAULT, null)
        )
    );
}

/**
 * Describes the return value of the add_new_course_module_quiz function.
 * @return external_single_structure
 */
public static function local_sync_service_add_new_course_module_quiz_returns() {
    return new external_single_structure(
        array(
            'message' => new external_value(PARAM_TEXT, 'If the execution was successful.'),
            'id' => new external_value(PARAM_INT, 'CMID of the new quiz module.')
        )
    );
}

/**
 * Method to create a new course module containing a Quiz.
 */
public static function local_sync_service_add_new_course_module_quiz(
    // Required parameters (match _parameters order)
    $courseid, $sectionnum, $quizname, $intro, $visible,
    // Optional parameters (match _parameters order and PHP defaults)
    $timeopen = 0, $timeclose = 0, $timelimit = 0,
    $grade = 10.0, $attempts = 0, $grademethod = QUIZ_GRADEHIGHEST, // QUIZ_GRADEHIGHEST default
    $questionsperpage = 0,
    $introformat = FORMAT_HTML, $moduleavailabilitytime = null, $beforemod = null
) {
    global $DB, $CFG;

    error_log("QUIZ_DEBUG: Entered add_new_course_module_quiz function.");
    // if (!empty($CFG->debugdeveloper)) { mtrace("QUIZ_MTRACE: Entered."); }

    require_once($CFG->dirroot . '/mod/quiz/lib.php');
    require_once($CFG->dirroot . '/mod/quiz/locallib.php'); // Often needed for quiz constants/helpers
    require_once($CFG->dirroot . '/course/lib.php');

    // Define QUIZ_GRADEHIGHEST if not already available from includes
    if (!defined('QUIZ_GRADEHIGHEST')) { define('QUIZ_GRADEHIGHEST', 1); }


    // Parameter validation
    $params = self::validate_parameters(
        self::local_sync_service_add_new_course_module_quiz_parameters(),
        array( // Ensure this array's keys map to _parameters and values match PHP arg order
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'quizname' => $quizname,
            'intro' => $intro,
            'visible' => $visible,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
            'grade' => $grade,
            'attempts' => $attempts,
            'grademethod' => $grademethod,
            'questionsperpage' => $questionsperpage,
            'introformat' => $introformat,
            'moduleavailabilitytime' => $moduleavailabilitytime,
            'beforemod' => $beforemod,
        )
    );
    error_log("QUIZ_DEBUG: Parameters after validation: " . var_export($params, true));

    // Context and capability
    $context = context_course::instance($params['courseid']);
    self::validate_context($context);
    require_capability('mod/quiz:addinstance', $context);

    // --- Step 1: Prepare CM object & Call add_course_module first ---
    $modulename = 'quiz';
    $cm = new \stdClass();
    $cm->course = $params['courseid'];
    $cm->module = $DB->get_field('modules', 'id', array('name' => $modulename), MUST_EXIST);
    $cm->instance = 0;
    $cm->idnumber = ''; // Default

    $target_section_number = $params['sectionnum'];
    // ... (logic to set $cm->section to actual_section_db_id or target_section_number, as in Forum/Label) ...
    $actual_section_db_id = $DB->get_field('course_sections', 'id', array('course' => $params['courseid'], 'section' => $target_section_number));
    if ($actual_section_db_id === false) { $cm->section = $target_section_number; } else { $cm->section = $actual_section_db_id; }

    $cm->visible = $params['visible'] ? 1 : 0;
    if (!is_null($params['moduleavailabilitytime'])) { // Using renamed param
        $availabilitytime = (int)$params['moduleavailabilitytime'];
        if ($availabilitytime < 0) { throw new \invalid_parameter_exception('Invalid overall availability time.'); }
        if ($availabilitytime > 0) {
            $showc_boolean = (bool)$cm->visible;
            $availabilityinfo = ["op"=>"&","c"=>[["type"=>"date","d"=>">=","t"=>$availabilitytime]],"showc"=>[$showc_boolean]];
            $cm->availability = json_encode($availabilityinfo);
        }
    }

    error_log("QUIZ_DEBUG: CM object FINAL BEFORE add_course_module: " . var_export($cm, true));
    $cmid = add_course_module($cm);
    error_log("QUIZ_DEBUG: AFTER add_course_module, returned cmid = " . var_export($cmid, true));
    // ... (log $cm_db_record_after_add) ...

    if (!$cmid) { throw new \moodle_exception('erroraddcoursemodule', 'webservice'); }

    // --- Step 2: Prepare $instance object for quiz_add_instance ---
    $instance = new \stdClass();
    $instance->course = $params['courseid'];
    $instance->name = $params['quizname'];
    $instance->intro = $params['intro'];
    $instance->introformat = $params['introformat'];

    $instance->timeopen = (int)$params['timeopen'];
    $instance->timeclose = (int)$params['timeclose'];
    $instance->timelimit = (int)$params['timelimit']; // Parameter should be in seconds.

    $instance->grade = (float)$params['grade']; // Ensure it's float
    $instance->attempts = (int)$params['attempts'];
    $instance->grademethod = (int)$params['grademethod'];
    $instance->questionsperpage = (int)$params['questionsperpage'];

    // Defaults for quiz table fields not directly exposed or complex:
    $instance->preferredbehaviour = ''; // Default to site/course category default question behaviour
    $instance->attemptonlast = 0;
    $instance->shuffleanswers = 1; // Common default
    $instance->questiondecimalpoints = -1; // Default for question grades
    $instance->sumgrades = $instance->grade; // sumgrades often matches the quiz max grade initially
    $instance->navmethod = 'free'; // Common default
    $instance->showuserpicture = 0;
    $instance->showblocks = 0;
    $instance->overduehandling = 'autosubmit'; // Common default
    $instance->graceperiod = 0;         // If overduehandling is 'graceperiod', this is seconds

    // Time created and modified will be set by quiz_add_instance if not present,
    // but no harm in setting them if you have them (or use time() as quiz_add_instance does).
    // $instance->timecreated = time(); // quiz_add_instance sets this
    $instance->timemodified = time(); // quiz_add_instance sets this

    // FIX FOR "password cannot be null"
    $instance->quizpassword = ''; // Default to no password (empty string)

    // Review options (bitmask values)
    // These often default to very restrictive settings (show nothing).
    // The value 65536 seen in your INSERT for reviewattempt is QUIZ_REVIEW_IMMEDIATELY | QUIZ_REVIEW_ATTEMPT
    // (assuming QUIZ_REVIEW_IMMEDIATELY = 65536 and QUIZ_REVIEW_ATTEMPT = 1, but this is not standard,
    // QUIZ_REVIEW_ATTEMPT is usually 1, QUIZ_REVIEW_IMMEDIATELY is a state, not a bitmask value itself).
    // Let's use common Moodle defaults for "After the quiz is closed"
    // These are bitmasks. 0 means nothing shown.
    // quiz_add_instance will set these based on site defaults if not provided.
    // To be safe, let's provide some very basic "off" defaults.
    // Actual values for what is shown are combinations of constants like QUIZ_REVIEW_ATTEMPT, QUIZ_REVIEW_CORRECTNESS etc.
    // Site defaults are often: Attempt (1), Marks (4), Overall feedback (32) after quiz is closed.
    // For now, let's set them to 0 and let quiz_add_instance apply its own internal defaults.
    // The value 65536 is unusual for reviewattempt; it's likely a specific configuration.
    // Defaulting to 0 for all is safest if not explicitly controlled by API params.
    $instance->reviewattempt          = 0; // Example: No review of attempt details
    $instance->reviewcorrectness      = 0; // Example: No review of whether correct
    $instance->reviewmarks            = 0; // Example: No review of marks
    $instance->reviewspecificfeedback = 0; // Example: No review of specific feedback
    $instance->reviewgeneralfeedback  = 0; // Example: No review of general feedback
    $instance->reviewrightanswer      = 0; // Example: No review of right answer
    $instance->reviewoverallfeedback  = 0; // Example: No review of overall feedback
    // The value 65536 for reviewattempt in your log is QUIZ_REVIEW_IMMEDIATELY_ATTEMPT from Moodle 2.x era.
    // For Moodle 3.x/4.x, review options are typically set differently.
    // quiz_add_instance in later Moodles often defaults these to quiz_get_review_options_after_close_defaults().
    // Let's rely on quiz_add_instance to set these review options defaults.
    // The INSERT statement shows it was trying to insert values, so they were likely defaulted by quiz_add_instance
    // just before the DB call. The password=NULL was the hard stop.

    // These were already in your "robust" setup for Forum/Page
    $instance->idnumber = isset($cm->idnumber) ? $cm->idnumber : ''; // Carry over
    $instance->coursemodule = $cmid;    // CRITICAL for your Moodle version
    $instance->completionexpected = 0;  // Default
    // Quiz doesn't have 'assessed' and 'scale' in the same way forum ratings do for its main table.
    // The main grade is $instance->grade.


    error_log("QUIZ_DEBUG: Instance object BEFORE quiz_add_instance: " . var_export($instance, true));

    // --- Step 3: Call quiz_add_instance ---
    $instanceid = quiz_add_instance($instance, null); // $mform is null
    error_log("QUIZ_DEBUG: AFTER quiz_add_instance, instanceid (quiz.id) = " . var_export($instanceid, true));

    if (!$instanceid) {
        course_delete_module($cmid);
        throw new \moodle_exception('erroraddquizinstance', 'mod_quiz');
    }

    // --- EXPLICIT UPDATE OVERRIDE for course_modules.instance ---
    $current_cm_instance_val = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("ASSIGN_DEBUG: DB CHECK BEFORE EXPLICIT INSTANCE UPDATE: course_modules.instance for cmid $cmid = " . var_export($current_cm_instance_val, true));
    if (($current_cm_instance_val == 0 || is_null($current_cm_instance_val)) && $instanceid > 0) {
        error_log("ASSIGN_DEBUG: course_modules.instance is 0/NULL for cmid $cmid. Attempting EXPLICIT update to instance $instanceid.");
        if ($DB->set_field('course_modules', 'instance', $instanceid, array('id' => $cmid))) {
            error_log("ASSIGN_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid to $instanceid SUCCEEDED.");
        } else {
            error_log("ASSIGN_DEBUG: EXPLICIT update of course_modules.instance for cmid $cmid FAILED. Rolling back.");
            course_delete_module($cmid); $DB->delete_records('assign', array('id' => $instanceid)); // Delete assign instance
            throw new \moodle_exception('errorupdatelinkcmassign', 'local_sync_service', '', null, "Failed to link assign instance $instanceid to CM $cmid.");
        }
    } // ... (else log if no update needed or $instanceid was bad) ...
    $final_cm_instance_value = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    error_log("ASSIGN_DEBUG: DB CHECK FINAL for instance: course_modules.instance for cmid $cmid = " . var_export($final_cm_instance_value, true));


// --- MANUAL SEQUENCE UPDATE (WORKAROUND) ---
    $final_section_db_id_for_sequence = $DB->get_field('course_modules', 'section', array('id' => $cmid));
    if ($final_section_db_id_for_sequence !== false) {
        $section_obj = $DB->get_record('course_sections', array('id' => $final_section_db_id_for_sequence, 'course' => $params['courseid']));
        if ($section_obj) {
            // ... (your full sequence update logic from Forum/Label) ...
            $current_sequence_array = array_filter(explode(',', $section_obj->sequence));
            $cmid_is_in_sequence = in_array((string)$cmid, $current_sequence_array);
            if (!$cmid_is_in_sequence) {
                error_log("ASSIGN_DEBUG: cmid $cmid NOT found in section (ID: $final_section_db_id_for_sequence) sequence '{$section_obj->sequence}'. Attempting manual add.");
                $current_sequence_array[] = (string)$cmid;
                $section_obj->sequence = implode(',', array_unique(array_filter($current_sequence_array)));
                if (!$DB->update_record('course_sections', $section_obj)) {
                    error_log("ASSIGN_DEBUG: MANUAL update of section sequence FAILED for section ID $final_section_db_id_for_sequence.");
                } else {
                     error_log("ASSIGN_DEBUG: MANUAL update of section sequence SUCCEEDED. New sequence: " . $section_obj->sequence);
                }
            }
        } else { error_log("ASSIGN_DEBUG: MANUAL update of section sequence FAILED for section ID $final_section_db_id_for_sequence."); }
    } else { error_log("ASSIGN_DEBUG: MANUAL update of section sequence FAILED for section ID $final_section_db_id_for_sequence."); }




    rebuild_course_cache($params['courseid']);

    return [
        'message' => 'Quiz shell added successfully.',
        'id' => $cmid,
    ];
}


    /**
     * Method to upload ZIP file in book so it appears as chapters in Moodle
     *
     * @param $cmid Course module id
     * @param $itemid Item id
     * @param $type Type of import
     * @return $update Message: Successful and return value 0 if ok
     */
    public static function local_sync_service_import_html_in_book($cmid, $itemid, $type) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/' . '/book' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/book' . '/locallib.php');
        require_once($CFG->dirroot . '/mod/' . '/book/tool/importhtml' . '/locallib.php');

        debug("local_sync_service_import_html_in_book");
        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_import_html_in_book_parameters(),
            array(
                'cmid' => $cmid,
                'itemid' => $itemid,
                'type' => $type
            )
        );

        // Ensure the current user has required permission in this course.
        $cm = get_coursemodule_from_id('book', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        $book = $DB->get_record('book', array('id'=>$cm->instance), '*', MUST_EXIST);

        require_capability('booktool/importhtml:import', $context);

        $fs = get_file_storage();
        debug("get info about itemid $itemid");
        if (!$files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $itemid, 'id', false)) {
              debug("no itemid $itemid found");
              $update = ['message' => 'Itemid not found','rv' => -1];
        }
        else {
            $file = reset($files);
            if ($file->get_mimetype() != 'application/zip') {
                debug("$itemid is not a zip content");
                $update = ['message' => 'Not a zip content','rv' => -1];
            }
            else{
                debug("all clear, let's go");
                toolbook_importhtml_import_chapters($file, $type, $book, $context, false);
                $update = ['message' => 'Successful','rv' => 0];
            }
        }
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_import_html_in_book_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'rv' => new external_value( PARAM_TEXT, 'return value' ),
            )
        );
    }

//

   /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_delete_all_chapters_from_book_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'course module id of book' )
            )
        );
    }


    /**
     * Method to delete all chapters in a book
     *
     * @param $cmid Course module id
     * @return $update Message: Successful and return value 0 if ok
     */
    public static function local_sync_service_delete_all_chapters_from_book($cmid) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/' . '/book' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/book' . '/locallib.php');

        debug("local_course_delete_all_chapters_from_book\n");
        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_delete_all_chapters_from_book_parameters(),
            array(
                'cmid' => $cmid,
            )
        );

        // Ensure the current user has required permission in this course.
        $cm = get_coursemodule_from_id('book', $cmid, 0, false, MUST_EXIST);
        debug("module id  $cm->id\n");
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/book:edit', $context);

        $fs = get_file_storage();
        $book = $DB->get_record('book', array('id'=>$cm->instance), '*', MUST_EXIST);
        debug("book id $book->id\n");

        $chapter = $DB->get_records('book_chapters', array('bookid' => $book->id), 'pagenum', 'id, pagenum,subchapter, title, content, contentformat, hidden');
        foreach ($chapter as $id => $ch) {
            debug("in chapter $ch->id\n");


            $subchaptercount = 0;
            if (!$chapter->subchapter) {
                // This is a top-level chapter.
                // Make sure to remove any sub-chapters if there are any.
                $chapters = $DB->get_recordset_select('book_chapters', 'bookid = :bookid AND pagenum > :pagenum', [
                        'bookid' => $book->id,
                        'pagenum' => $chapter->pagenum,
                    ], 'pagenum');

                foreach ($chapters as $ch) {
                    debug("get chapter $ch->id\n");
                    if (!$ch->subchapter) {
                        // This is a new chapter. Any subsequent subchapters will be part of a different chapter.
                        break;
                    } else {
                        // This is subchapter of the chapter being removed.
                        core_tag_tag::remove_all_item_tags('mod_book', 'book_chapters', $ch->id);
                        $fs->delete_area_files($context->id, 'mod_book', 'chapter', $ch->id);
                        $DB->delete_records('book_chapters', ['id' => $ch->id]);

                        $subchaptercount++;
                    }
                }
                $chapters->close();
            }
            else
                debug("no subcharters to delete\n");

            // Now delete the actual chapter.
            debug("delete chapter $ch->id\n");
            core_tag_tag::remove_all_item_tags('mod_book', 'book_chapters', $ch->id);
            $fs->delete_area_files($context->id, 'mod_book', 'chapter', $ch->id);
            $DB->delete_records('book_chapters', ['id' => $ch->id]);
        }

        // Ensure that the book structure is correct.
        // book_preload_chapters will fix parts including the pagenum.
        $chapters = book_preload_chapters($book);

        book_add_fake_block($chapters, $chapter, $book, $cm);

        // Bump the book revision.
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);

        debug("all clear, let's go");
        $update = ['message' => 'Successful','rv' => 0];

        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_delete_all_chapters_from_book_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'rv' => new external_value( PARAM_TEXT, 'return value' ),
            )
        );
    }


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_update_course_module_resource_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of the upload' ),
                'displayname' => new external_value( PARAM_TEXT, 'displayed mod name', VALUE_DEFAULT, null  )
            )
        );
    }

    /**
     * Method to update a new course module containing a file.
     *
     * @param $courseid The course id.
     * @param $itemid File to publish.
     * @param $displayname Displayname of resource (optional)
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_update_course_module_resource($cmid, $itemid, $displayname) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/resource' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/resource' . '/locallib.php');
        require_once($CFG->dirroot . '/course/' . '/modlib.php');
        require_once($CFG->dirroot . '/availability/' . '/condition' . '/date' . '/classes' . '/condition.php');

        debug("local_sync_service_update_course_module_resource\n");
        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_update_course_module_resource_parameters(),
            array(
                'cmid' => $cmid,
                'itemid' => $itemid,
                'displayname' => $displayname
            )
        );

        $cm = get_coursemodule_from_id('resource', $cmid, 0, false, MUST_EXIST);
        debug("module instance id  $cm->instance\n");

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($cm->course);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/resource:addinstance', $context);

        $modulename = 'resource';
        $instance = new \stdClass();
        $instance->course = $cm->course;
        $instance->intro = "";
        $instance->introformat = \FORMAT_HTML;
        $instance->coursemodule = $cmid;
        $instance->files = $itemid;
        $instance->instance = $cm->instance;
        $instance->modulename = $modulename;
        $instance->type = 'mod';
        //display name is optional
        if (!is_null($params['displayname'])) {
            $instance->name = $params['displayname'];
        } else {
            $instance->name = $cm->name;
        }

        $instance->id = resource_update_instance($instance, null);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $moduleinfo = edit_module_post_actions($instance, $course);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_update_course_module_resource_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_update_course_module_label_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'htmlbody' => new external_value( PARAM_TEXT, 'HTML name', VALUE_DEFAULT, null  ),
            )
        );
    }

    /**
     * Method to update a new course module containing a file.
     *
     * @param $cmid The course module id.
     * @param $htmlbody HTML code to add to body
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_update_course_module_label($cmid, $htmlbody) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/availability/' . '/condition' . '/date' . '/classes' . '/condition.php');
        require_once($CFG->dirroot . '/mod/' . '/label' . '/lib.php');
        require_once($CFG->dirroot . '/course/' . '/modlib.php');

        debug("local_sync_service_update_course_module_label\n");
        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_update_course_module_label_parameters(),
            array(
                'cmid' => $cmid,
                'htmlbody' => $htmlbody
            )
        );

        $cm = get_coursemodule_from_id('label', $cmid, 0, false, MUST_EXIST);

        // Ensure the current user has required permission in this course.
        $context = context_course::instance($cm->course);
        self::validate_context($context);


        // Required permissions.
        require_capability('mod/label:addinstance', $context);

        $modulename = 'label';
        $cm->module     = $DB->get_field( 'modules', 'id', array('name' => $modulename) );
        $instance = new \stdClass();
        $instance->course = $cm->course;
        $instance->intro = $htmlbody;

        $instance->introformat = \FORMAT_HTML;
        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->modulename = $modulename;
        $instance->type = 'mod';
        $instance->visible = true;
        $instance->id = label_update_instance($instance, null);

        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        $moduleinfo = edit_module_post_actions($instance, $course);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_update_course_module_label_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_update_course_module_page_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'content' => new external_value( PARAM_TEXT, 'HTML or Markdown code'  ),
                'format' => new external_value( PARAM_TEXT, 'Markdown or HTML', VALUE_DEFAULT, \FORMAT_MARKDOWN  ),
            )
        );
    }

    /**
     * Method to update a new course module containing a file.
     *
     * @param $cmid The course module id.
     * @param $content Content to add
     * @param $format HTML or Markdown(=default)
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_update_course_module_page($cmid, $content, $format) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/page' . '/lib.php');
        require_once($CFG->dirroot . '/course/' . '/modlib.php');

        //debug("local_sync_service_update_course_module_page\n");
        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_update_course_module_page_parameters(),
            array(
                'cmid' => $cmid,
                'content' => $content,
                'format' => $format
            )
        );

        $cm = get_coursemodule_from_id('page', $cmid, 0, false, MUST_EXIST);

        // Ensure the current user has required permission in this course.
        $context = context_module::instance($cmid);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/page:addinstance', $context);

        $modulename = 'page';
        $cm->module = $DB->get_field( 'modules', 'id', array('name' => $modulename) );
        $instance = new \stdClass();
        $instance->course = $cm->course;
        $instance->contentformat = $format;
        $instance->page =  [
            'text' => html_entity_decode($content),
            'format' =>  $format,
        ];

        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->modulename = $modulename;
        $instance->type = 'mod';
        $instance->visible = true;
        $instance->id = page_update_instance($instance, null);

        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_update_course_module_page_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }



    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */

     public static function local_sync_service_update_course_module_assignment_parameters() {
        return new external_function_parameters(
            array(
                'assignments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'cmid' => new external_value(PARAM_INT, 'ID of the assignment module'),
                            'desc' => new external_value(PARAM_TEXT, 'description of assisngment'),
                            'activity' => new external_value(PARAM_TEXT, 'activity in assignment', VALUE_OPTIONAL)
                        )
                    ), 'assignment courses to update'
                )
            )
        );
    }

    /**
     * Method to update a new course module containing a assignment.
     *
     * @param $cmid The course module id.
     * @param $desc  HTML code to add to description
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_update_course_module_assignment($assignments) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/assign' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/assign' . '/locallib.php');
        $warnings = array();

        //debug("local_sync_service_update_course_module_assignment\n");

        $params = self::validate_parameters(self::local_sync_service_update_course_module_assignment_parameters(), array('assignments' => $assignments));

        foreach ($params['assignments'] as $ass) {
            try {
                $cmid = $ass['cmid'];
                $desc = $ass['desc'];
                //debug(" cmid=$cmid , desc=$desc\n");

                if (array_key_exists('activity', $ass) ) {
                    $activity = $ass['activity'];
                }

            }catch (Exception $e) {
                debug(" exception\n");
                $warning = array();
                $warning['item'] = 'assignments';
                $warning['itemid'] = $ass['cmid'];
                if ($e instanceof moodle_exception) {
                    $warning['warningcode'] = $e->errorcode;
                } else {
                    $warning['warningcode'] = $e->getCode();
                }
                $warning['message'] = $e->getMessage();
                $warnings[] = $warning;
            }
        }

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        // Ensure the current user has required permission in this course.
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/assign:addinstance', $context);

        $dbparams = array('id'=>$cm->instance);
        if (! $instance = $DB->get_record('assign', $dbparams, '*')) {
            return false;
        }

        $instance->id = $cm->instance;

        $instance->activityeditor =  [
            'text' => html_entity_decode($activity),
            'format' =>  \FORMAT_MARKDOWN,
        ];

        $instance->introformat = \FORMAT_MARKDOWN;
        $instance->activityformat = \FORMAT_MARKDOWN;
        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->modulename ='assign';
        $instance->type = 'mod';
        $instance->visible = true;

        $instance->intro = html_entity_decode($desc);
        $instance->id = assign_update_instance($instance, null);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];

        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_update_course_module_assignment_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }

         /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */

     public static function local_sync_service_update_course_module_lesson_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_INT, 'id of module' ),
                'desc' => new external_value( PARAM_TEXT, 'description'  )               
            )
        );
    }

    /**
     * Method to update a lesson module 
     *
     * @param $cmid The course module id.
     * @param $desc  content to add to description
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_update_course_module_lesson($cmid, $desc) {
        global $DB, $CFG;
        //debug("local_sync_service_update_course_module_lesson");
        
        require_once($CFG->dirroot . '/mod/' . '/lesson' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/lesson' . '/locallib.php');
              

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_update_course_module_lesson_parameters(),
            array(
                'cmid' => $cmid,
                'desc' => $desc
            )
        );

       
        $cm = get_coursemodule_from_id('lesson', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('lesson', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/lesson:addinstance', $context);

        $instance->intro=html_entity_decode($desc);
        $instance->introformat = \FORMAT_MARKDOWN;        
        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->modulename ='lesson';
        $instance->type = 'mod';
        $instance->visible = true;        
       
        $instance->id = lesson_update_instance($instance, null);
 
        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];

        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_update_course_module_lesson_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */

     public static function local_sync_service_update_course_module_lesson_contentpage_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_INT, 'id of module' ),
                'pageid' => new external_value( PARAM_INT, 'pageid of lesson content' ),
                'title' => new external_value( PARAM_TEXT, 'title of lesson content page',VALUE_OPTIONAL ),
                'content' => new external_value( PARAM_TEXT, 'HTML or Markdown code'  ),
               
            )
        );
    }

    /**
     * Method to update a lesson module and add a content page to it.
     *
     * @param $cmid The cours $title,e module id.
     * @param $desc  content to add to description
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_update_course_module_lesson_contentpage($cmid, $pageid, $title, $content) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/' . '/lesson' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/lesson' . '/locallib.php');
        $warnings = array();

        //debug("local_sync_service_update_course_module_lesson_contentpage\n");

          // Parameter validation.
         $params = self::validate_parameters(
            self::local_sync_service_update_course_module_lesson_contentpage_parameters(),
            array(
                'cmid' => $cmid,
                'pageid' => $pageid,
                'title' => $title,
                'content' => $content                
            )
        );
        $cm = get_coursemodule_from_id('lesson', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('lesson', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/lesson:addinstance', $context);
        
        $lesson = new Lesson($instance);
        $page = $lesson->load_page($pageid);
        $prop = $page->properties();
        $prop->contents=html_entity_decode($content);        
        if (!empty($title)) {            
            $prop->title=$title;
        }
        $DB->update_record("lesson_pages", $prop);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];

        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_update_course_module_lesson_contentpage_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }



     /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_assignment_save_attachment_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of draft area where file uploaded' ),
                'filename' => new external_value( PARAM_TEXT, 'filename' )
            )
        );
    }

    /**
     * Method to update a new course module containing a file.
     *
     * @param $courseid The course id.
     * @param $itemid File to publish.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_assignment_save_attachment($cmid, $itemid, $filename) {
        global $DB, $CFG,$USER;

        require_once($CFG->dirroot . '/mod/' . '/assign' . '/lib.php');
        require_once($CFG->dirroot . '/mod/' . '/assign' . '/locallib.php');

        //debug("local_sync_service_assignment_save_attachment\n");

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_assignment_save_attachment_parameters(),
            array(
                'cmid' => $cmid,
                'itemid' => $itemid,
                'filename' => $filename
            )
        );

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('assign', array('id' => $cm->instance), '*', MUST_EXIST);

        // Ensure the current user has required permission in this course.
        $context = context_module::instance($cmid);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/assign:addinstance', $context);
        require_capability('moodle/course:managefiles', $context);

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);

        $files = $fs->get_area_files($context->id, 'mod_assign', 'intro'/*ASSIGN_INTROATTACHMENT_FILEAREA*/, 0);
        foreach ($files as $file) {
            if  ($file->get_filename() == $filename /*and $file->get_itemid() == $itemid*/) {
                $file->delete();
            }
        }

        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $itemid);
        
        foreach ($files as $file) {

            $fileinfo = [
                'contextid' =>  $context->id,   // ID of the context.
                'component' => 'mod_assign', // Your component name.
                'filearea'  => 'intro', //ASSIGN_INTROATTACHMENT_FILEAREA,       // Usually = table name.
                'itemid'    =>  0,              // Usually = ID of row in table.
                'filepath'  =>  '/',            // Any path beginning and ending in /.
                'filename'  =>  $file->get_filename(),   // Any filename.
            ];

            if  ($file->get_filename() == $filename /*and $file->get_itemid() == $itemid*/ ) {
                $fs->create_file_from_storedfile($fileinfo, $file);

                $url = moodle_url::make_draftfile_url(
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );

                break;
            }

        }

        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->id = $cm->instance;
        $instance->id = assign_update_instance($instance, null);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_assignment_save_attachment_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
                //'url' => new external_value( PARAM_TEXT, 'url of the uploaded itemid' ),
            )
        );
    }


    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_label_save_attachment_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of draft area where file uploaded' ),
                'filename' => new external_value( PARAM_TEXT, 'filename' )
            )
        );
    }

    /**
     *
     * @param $cmdid The  id of the label module
     * @param $itemid File to publish.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_label_save_attachment($cmid, $itemid, $filename) {
        global $DB, $CFG,$USER;

        require_once($CFG->dirroot . '/mod/' . '/label' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_label_save_attachment_parameters(),
            array(
                'cmid' => $cmid,
                'itemid' => $itemid,
                'filename' => $filename
            )
        );

        //debug(" cmid=$cmid , itemid=$itemid, filename=$filename\n");

        $cm = get_coursemodule_from_id('label', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('label', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/label:addinstance', $context);
        require_capability('moodle/course:managefiles', $context);

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'mod_label', 'intro', 0);
        foreach ($files as $file) {
            if  ($file->get_filename() == $filename) {
                $file->delete();
            }

        }

        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $itemid);
        foreach ($files as $file) {
            $fileinfo = [
                'contextid' =>  $context->id,   // ID of the context.
                'component' => 'mod_label', // Your component name.
                'filearea'  => 'intro',
                'itemid'    =>  0,              // Usually = ID of row in table.
                'filepath'  =>  '/',            // Any path beginning and ending in /.
                'filename'  =>  $file->get_filename(),   // Any filename.
            ];

            if  ($file->get_filename() == $filename ) {
                // debug("create store file for $filename ($itemid)\n");
                $fs->create_file_from_storedfile($fileinfo, $file);

                $url = moodle_url::make_draftfile_url(
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );
                // debug("Draft URL: $url\n");
                break;
            }

        }

        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->id = $cm->instance;
        $instance->id = label_update_instance($instance);


        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_label_save_attachment_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }

         /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_page_save_attachment_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of draft area where file uploaded' ),
                'filename' => new external_value( PARAM_TEXT, 'filename' )
            )
        );
    }

    /**
     *
     * @param $cmdid The  id of the label module
     * @param $itemid File to publish.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_page_save_attachment($cmid, $itemid, $filename) {
        global $DB, $CFG,$USER;

        require_once($CFG->dirroot . '/mod/' . '/page' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_page_save_attachment_parameters(),
            array(
                'cmid' => $cmid,
                'itemid' => $itemid,
                'filename' => $filename
            )
        );

        $cm = get_coursemodule_from_id('page', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('page', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/page:addinstance', $context);
        require_capability('moodle/course:managefiles', $context);

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'mod_page', 'content', 0);
        foreach ($files as $file) {
            if  ($file->get_filename() == $filename) {
                $file->delete();
            }
        }

        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $itemid);
        foreach ($files as $file) {

            $fileinfo = [
                'contextid' =>  $context->id,   // ID of the context.
                'component' => 'mod_page', // Your component name.
                'filearea'  => 'content',
                'itemid'    =>  0,              // Usually = ID of row in table.
                'filepath'  =>  '/',            // Any path beginning and ending in /.
                'filename'  =>  $file->get_filename(),   // Any filename.
            ];

            if  ($file->get_filename() == $filename ) {
                $fs->create_file_from_storedfile($fileinfo, $file);
                break;
            }
        }

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_page_save_attachment_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }

         /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_lesson_save_attachment_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of draft area where file uploaded' ),
                'filename' => new external_value( PARAM_TEXT, 'filename' )
            )
        );
    }


    /**
     *
     * @param $cmdid The  id of the label module
     * @param $itemid File to publish.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_lesson_save_attachment($cmid, $itemid, $filename) {
        global $DB, $CFG,$USER;

        require_once($CFG->dirroot . '/mod/' . '/lesson' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_lesson_save_attachment_parameters(),
            array(
                'cmid' => $cmid,
                'itemid' => $itemid,
                'filename' => $filename
            )
        );

        $cm = get_coursemodule_from_id('lesson', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('lesson', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/lesson:addinstance', $context);
        require_capability('moodle/course:managefiles', $context);

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'mod_lesson', 'intro', 0);
        foreach ($files as $file) {
            if  ($file->get_filename() == $filename) {
                $file->delete();
            }

        }

        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $itemid);
        foreach ($files as $file) {
            $fileinfo = [
                'contextid' =>  $context->id,   // ID of the context.
                'component' => 'mod_lesson', // Your component name.
                'filearea'  => 'intro',
                'itemid'    =>  0,              // Usually = ID of row in table.
                'filepath'  =>  '/',            // Any path beginning and ending in /.
                'filename'  =>  $file->get_filename(),   // Any filename.
            ];

            if  ($file->get_filename() == $filename ) {
                $fs->create_file_from_storedfile($fileinfo, $file);

                $url = moodle_url::make_draftfile_url(
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );
                break;
            }

        }

        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->id = $cm->instance;        
        $instance->id = lesson_update_instance($instance,null);
        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_lesson_save_attachment_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }



    /**
     * Defines the necessary method parameters.
     * @return external_function_parameters
     */
    public static function local_sync_service_lessonpage_save_attachment_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value( PARAM_TEXT, 'id of module' ),
                'itemid' => new external_value( PARAM_TEXT, 'id of draft area where file uploaded' ),
                'pageid' => new external_value( PARAM_TEXT, 'pageid of lesson content page' ),
                'filename' => new external_value( PARAM_TEXT, 'filename' )
            )
        );
    }


    /**
     *
     * @param $cmdid The  id of the lesson module
     * @param $itemid File to publish.
     * @return $update Message: Successful and $cmid of the new Module.
     */
    public static function local_sync_service_lessonpage_save_attachment($cmid, $itemid, $pageid, $filename) {
        global $DB, $CFG,$USER;

        require_once($CFG->dirroot . '/mod/' . '/lesson' . '/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::local_sync_service_lessonpage_save_attachment_parameters(),
            array(
                'cmid' => $cmid,
                'itemid' => $itemid,
                'pageid' => $pageid,
                'filename' => $filename
            )
        );

        $cm = get_coursemodule_from_id('lesson', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('lesson', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        // Required permissions.
        require_capability('mod/lesson:addinstance', $context);
        require_capability('moodle/course:managefiles', $context);

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'mod_lesson', 'page_contents', $pageid);
        foreach ($files as $file) {
            if  ($file->get_filename() == $filename) {
                $file->delete();
            }
        }

        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $itemid);
        foreach ($files as $file) {
        
            $fileinfo = [
                'contextid' =>  $context->id,   // ID of the context.
                'component' => 'mod_lesson', // Your component name.
                'filearea'  => 'page_contents',
                'itemid'    =>  $pageid,              // ID of row in table mdl_lesson_page
                'filepath'  =>  '/',            // Any path beginning and ending in /.
                'filename'  =>  $file->get_filename(),   // Any filename.
            ];

            if  ($file->get_filename() == $filename ) {
                $fs->create_file_from_storedfile($fileinfo, $file);

                $url = moodle_url::make_draftfile_url(
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );
                break;
            }

        }

        $instance->coursemodule = $cmid;
        $instance->instance = $cm->instance;
        $instance->id = $cm->instance;        
        $instance->id = lesson_update_instance($instance,null);

        $update = [
            'message' => 'Successful',
            'id' => $cmid,
        ];
        return $update;
    }

    /**
     * Obtains the Parameter which will be returned.
     * @return external_description
     */
    public static function local_sync_service_lessonpage_save_attachment_returns() {
        return new external_single_structure(
            array(
                'message' => new external_value( PARAM_TEXT, 'if the execution was successful' ),
                'id' => new external_value( PARAM_TEXT, 'cmid of the new module' ),
            )
        );
    }
}
