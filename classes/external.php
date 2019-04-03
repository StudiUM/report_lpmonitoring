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

/**
 * This is the external API for this report.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lpmonitoring;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use context;
use core_user;
use core_competency\plan;
use core_competency\url;
use core_competency\external as core_competency_external;
use core_competency\api as core_competency_api;
use core_user\external\user_summary_exporter;
use core_competency\user_competency;
use core_competency\external\competency_exporter;
use core_competency\external\plan_exporter;
use core_competency\external\template_exporter;
use core_competency\external\user_competency_exporter;
use core_competency\external\user_competency_plan_exporter;
use core_comment\external\comment_area_exporter;
use core_tag_tag;
use report_lpmonitoring\external\stats_plan_exporter;
use report_lpmonitoring\external\lpmonitoring_competency_detail_exporter;
use report_lpmonitoring\external\lpmonitoring_competency_statistics_exporter;
use report_lpmonitoring\external\lpmonitoring_competency_statistics_incourse_exporter;
use context_system;
use moodle_exception;


/**
 * This is the external API for this report.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * Returns description of search_users_by_templateid() parameters.
     *
     * @return \external_function_parameters
     */
    public static function search_users_by_templateid_parameters() {
        $templateid = new external_value(
            PARAM_INT,
            'The learning plan template id',
            VALUE_REQUIRED
        );

        $query = new external_value(
            PARAM_TEXT,
            'The query search',
            VALUE_REQUIRED
        );
        $scalevalues = new external_value(
            PARAM_TEXT,
            'The scale values filter',
            VALUE_DEFAULT,
            ''
        );
        $scalefilterbycourse = new external_value(
            PARAM_INT,
            'Apply scale filter on rate of course',
            VALUE_DEFAULT,
            '1'
        );
        $scalesortorder = new external_value(
            PARAM_TEXT,
            'Scale sort order',
            VALUE_DEFAULT,
            'ASC'
        );

        $params = array(
            'templateid' => $templateid,
            'query' => $query,
            'scalevalues' => $scalevalues,
            'scalefilterbycourse' => $scalefilterbycourse,
            'scalesortorder' => $scalesortorder
        );
        return new external_function_parameters($params);
    }

    /**
     * Get learning plans from templateid.
     *
     * @param int $templateid Template id.
     * @param string $query the query search.
     * @param string $scalevalues The scale values filter.
     * @param int $scalefilterbycourse Apply the scale filters on grade in course.
     * @param string $scalesortorder The scale sort order ('ASC'/'DESC').
     *
     * @return boolean
     */
    public static function search_users_by_templateid($templateid, $query, $scalevalues, $scalefilterbycourse, $scalesortorder) {
        global $PAGE;
        $params = self::validate_parameters(self::search_users_by_templateid_parameters(), array(
            'templateid' => $templateid,
            'query' => $query,
            'scalevalues' => $scalevalues,
            'scalefilterbycourse' => $scalefilterbycourse,
            'scalesortorder' => $scalesortorder
        ));

        $context = context_system::instance();
        self::validate_context($context);

        $records = api::search_users_by_templateid($params['templateid'], $params['query'],
                json_decode($params['scalevalues'], true), $params['scalefilterbycourse'], $params['scalesortorder']);

        foreach ($records as $key => $record) {
            $profileimage = $record['profileimage'];
            $profileimage->size = 0;
            $record['profileimagesmall']  = $profileimage->get_url($PAGE)->out(false);
            $records[$key] = $record;
        }
        return (array) (object) $records;
    }

    /**
     * Returns description of search_users_by_templateid() result value.
     *
     * @return \external_description
     */
    public static function search_users_by_templateid_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'fullname' => new external_value(PARAM_TEXT, 'The fullname of the user'),
                'profileimagesmall' => new external_value(PARAM_TEXT, 'The profile image small size'),
                'userid' => new external_value(PARAM_INT, 'The user id value'),
                'planid' => new external_value(PARAM_INT, 'The plan id value'),
                'nbrating' => new external_value(PARAM_INT, 'Total rating number'),
                'email' => new external_value(PARAM_TEXT, 'The email of the user', VALUE_OPTIONAL),
                'idnumber' => new external_value(PARAM_TEXT, 'The idnumber of the user', VALUE_OPTIONAL),
                'phone1' => new external_value(PARAM_TEXT, 'The phone1 of the user', VALUE_OPTIONAL),
                'phone2' => new external_value(PARAM_TEXT, 'The phone2 of the user', VALUE_OPTIONAL),
                'department' => new external_value(PARAM_TEXT, 'The department of the user', VALUE_OPTIONAL),
                'institution' => new external_value(PARAM_TEXT, 'The institution of the user', VALUE_OPTIONAL)
            ))
        );
    }

    /**
     * Returns description of search_templates() parameters.
     *
     * @return \external_function_parameters
     */
    public static function search_templates_parameters() {

        $query = new external_value(
            PARAM_TEXT,
            'The query search',
            VALUE_REQUIRED
        );
        $contextid = new external_value(
            PARAM_INT,
            'The context id',
            VALUE_REQUIRED
        );
        $skip = new external_value(
            PARAM_INT,
            'Number of records to skip',
            VALUE_DEFAULT,
            0
        );
        $limit = new external_value(
            PARAM_INT,
            'Max of records to return',
            VALUE_DEFAULT,
            0
        );
        $includes = new external_value(
            PARAM_TEXT,
            'Defines what other contexts to fetch templates',
            VALUE_DEFAULT,
            'children'
        );
        $onlyvisible = new external_value(
            PARAM_BOOL,
            'True if search in visible templates',
            VALUE_DEFAULT,
            true
        );

        $params = array(
            'contextid' => $contextid,
            'query' => $query,
            'skip' => $skip,
            'limit' => $limit,
            'includes' => $includes,
            'onlyvisible' => $onlyvisible
        );
        return new external_function_parameters($params);
    }

    /**
     * Search templates.
     *
     * @param int $contextid Context id.
     * @param string $query the query search.
     * @param int $skip Number of records to skip (pagination)
     * @param int $limit Max of records to return (pagination)
     * @param string $includes Defines what other contexts to fetch templates from.
     *                         Accepted values are:
     *                          - children: All descendants
     *                          - parents: All parents, grand parents, etc...
     *                          - self: Context passed only.
     * @param bool $onlyvisible If should search only in visible templates
     * @return boolean
     */
    public static function search_templates($contextid, $query, $skip, $limit, $includes, $onlyvisible ) {
        global $PAGE;
        $params = self::validate_parameters(self::search_templates_parameters(), array(
            'contextid' => $contextid,
            'query' => $query,
            'skip' => $skip,
            'limit' => $limit,
            'includes' => $includes,
            'onlyvisible' => $onlyvisible
        ));

        $context = self::get_context_from_params($params);
        self::validate_context($context);
        $output = $PAGE->get_renderer('core');

        $results = api::search_templates($context,
                $params['query'],
                $params['skip'],
                $params['limit'],
                $params['includes'],
                $params['onlyvisible']);

        $records = array();
        foreach ($results as $result) {
            $exporter = new template_exporter($result);
            $record = $exporter->export($output);
            array_push($records, $record);
        }
        return $records;
    }

    /**
     * Returns description of search_templates() result value.
     *
     * @return \external_description
     */
    public static function search_templates_returns() {
        return new external_multiple_structure(template_exporter::get_read_structure());
    }

    /**
     * Returns description of get_scales_from_template() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_scales_from_template_parameters() {
        $templateid = new external_value(
            PARAM_INT,
            'The learning plan template id',
            VALUE_REQUIRED
        );

        $params = array(
            'templateid' => $templateid
        );
        return new external_function_parameters($params);
    }

    /**
     * Get scales from templateid.
     *
     * @param int $templateid Template id.
     *
     * @return boolean
     */
    public static function get_scales_from_template($templateid) {
        $params = self::validate_parameters(self::get_scales_from_template_parameters(), array(
            'templateid' => $templateid
        ));

        $context = context_system::instance();
        self::validate_context($context);

        $results = api::get_scales_from_templateid($params['templateid']);
        $records = array();
        foreach ($results as $key => $value) {
            $scale = self::read_report_competency_config($value['frameworkid'], $key);
            $scale->name = $value['scalename'];
            $records[] = $scale;
        }
        return $records;
    }

    /**
     * Returns description of get_scales_from_template() result value.
     *
     * @return \external_description
     */
    public static function get_scales_from_template_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(PARAM_INT, 'The option value'),
                'name' => new external_value(PARAM_TEXT, 'The scale name'),
                'competencyframeworkid' => new external_value(PARAM_INT, 'The option value'),
                'scaleid' => new external_value(PARAM_INT, 'The option value'),
                'scaleconfiguration' => new external_multiple_structure(
                    new external_single_structure(array ('id' => new external_value(PARAM_INT, 'The option value'),
                        'name' => new external_value(PARAM_TEXT, 'The option value'),
                        'color' => new external_value(PARAM_TEXT, 'The option value'),
                        'proficient' => new external_value(PARAM_BOOL, 'The proficient indicator'))
                    ))
            ))
        );
    }

    /**
     * Returns description of get_scales_from_framework() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_scales_from_framework_parameters() {
        $competencyframeworkid = new external_value(
            PARAM_INT,
            'The competency framework id',
            VALUE_REQUIRED
        );

        $params = array(
            'competencyframeworkid' => $competencyframeworkid
        );
        return new external_function_parameters($params);
    }

    /**
     * Get scales from competencyframeworkid.
     *
     * @param int $competencyframeworkid Framework id.
     *
     * @return boolean
     */
    public static function get_scales_from_framework($competencyframeworkid) {

        $params = self::validate_parameters(self::get_scales_from_framework_parameters(), array(
            'competencyframeworkid' => $competencyframeworkid,
        ));

        return api::get_scales_from_framework($params['competencyframeworkid']);
    }

    /**
     * Returns description of get_scales_from_framework() result value.
     *
     * @return \external_description
     */
    public static function get_scales_from_framework_returns() {
        return new external_multiple_structure(
                    new external_single_structure(array(
                        'id' => new external_value(PARAM_INT, 'The option value'),
                        'name' => new external_value(PARAM_TEXT, 'The name of the scale'),
                    ))
            );
    }

    /**
     * Returns description of read_report_competency_config_parameters() parameters.
     *
     * @return \external_function_parameters
     */
    public static function read_report_competency_config_parameters() {
        $competencyframeworkid = new external_value(
            PARAM_INT,
            'The competency framework id',
            VALUE_REQUIRED
        );
        $scaleid = new external_value(
            PARAM_INT,
            'The scale id',
            VALUE_REQUIRED
        );
        $params = array(
            'competencyframeworkid' => $competencyframeworkid,
            'scaleid' => $scaleid
        );
        return new external_function_parameters($params);
    }

    /**
     * Read report competency configuration
     *
     * @param int $competencyframeworkid Framework id.
     * @param int $scaleid Scale id.
     * @return \stdClass The record of report_competency_config
     */
    public static function read_report_competency_config($competencyframeworkid, $scaleid) {

        $params = self::validate_parameters(self::read_report_competency_config_parameters(), array(
            'competencyframeworkid' => $competencyframeworkid,
            'scaleid' => $scaleid
        ));

        $reportcompetencyconfig = api::read_report_competency_config($params['competencyframeworkid'], $params['scaleid']);
        $scaleotherinfo = api::get_scale_configuration_other_info($params['competencyframeworkid'], $params['scaleid']);

        $record = new \stdClass();
        $record->id = $reportcompetencyconfig->get('id');
        $record->competencyframeworkid = $reportcompetencyconfig->get('competencyframeworkid');
        $record->scaleid = $reportcompetencyconfig->get('scaleid');
        $record->scaleconfiguration = array();
        $config = json_decode($reportcompetencyconfig->get('scaleconfiguration'));

        foreach ($config as $key => $valuescale) {
            $valuescale->proficient = $scaleotherinfo[$key]['proficient'];
            $valuescale->name = $scaleotherinfo[$key]['name'];
            $record->scaleconfiguration[] = (object) $valuescale;
        }

        return $record;
    }

    /**
     * Returns description of read_report_competency_config() result value.
     *
     * @return \external_description
     */
    public static function read_report_competency_config_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'The option value'),
            'competencyframeworkid' => new external_value(PARAM_INT, 'The option value'),
            'scaleid' => new external_value(PARAM_INT, 'The option value'),
            'scaleconfiguration' => new external_multiple_structure(
                new external_single_structure(array ('id' => new external_value(PARAM_INT, 'The option value'),
                    'name' => new external_value(PARAM_TEXT, 'The option value'),
                    'color' => new external_value(PARAM_TEXT, 'The option value'),
                    'proficient' => new external_value(PARAM_BOOL, 'The proficient indicator'))
                ))
            ));
    }

    /**
     * Returns description of read_report_competency_config_parameters() parameters.
     *
     * @return \external_function_parameters
     */
    public static function create_report_competency_config_parameters() {
        $competencyframeworkid = new external_value(
            PARAM_INT,
            'The competency framework id',
            VALUE_REQUIRED
        );
        $scaleid = new external_value(
            PARAM_INT,
            'The scale id',
            VALUE_REQUIRED
        );
        $scaleconfiguration = new external_value(
            PARAM_RAW,
            'The scaleconfiguration',
            VALUE_REQUIRED
        );

        $params = array(
            'competencyframeworkid' => $competencyframeworkid,
            'scaleid' => $scaleid,
            'scaleconfiguration' => $scaleconfiguration
        );

        return new external_function_parameters($params);
    }

    /**
     * Create report competency configuration
     *
     * @param int $competencyframeworkid Framework id.
     * @param int $scaleid Scale id.
     * @param string $scaleconfiguration Scale configuration.
     * @return stdClass The new record
     */
    public static function create_report_competency_config($competencyframeworkid, $scaleid, $scaleconfiguration) {
        global $PAGE;

        $params = self::validate_parameters(self::create_report_competency_config_parameters(), array(
            'competencyframeworkid' => $competencyframeworkid,
            'scaleid' => $scaleid,
            'scaleconfiguration' => $scaleconfiguration
        ));

        $params = (object) $params;
        $result = api::create_report_competency_config($params);
        $record = new \stdClass();
        $record->id = $result->get('id');
        $record->competencyframeworkid = $result->get('competencyframeworkid');
        $record->scaleid = $result->get('scaleid');
        $record->scaleconfiguration = array();

        $scaleotherinfo = api::get_scale_configuration_other_info($params->competencyframeworkid, $params->scaleid);
        $config = json_decode($result->get('scaleconfiguration'));
        foreach ($config as $key => $valuescale) {
            $valuescale->proficient = $scaleotherinfo[$key]['proficient'];
            $valuescale->name = $scaleotherinfo[$key]['name'];
            $record->scaleconfiguration[] = (object) $valuescale;
        }

        return $record;
    }

    /**
     * Returns description of read_report_competency_config() result value.
     *
     * @return \external_description
     */
    public static function create_report_competency_config_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'The option value'),
            'competencyframeworkid' => new external_value(PARAM_INT, 'The option value'),
            'scaleid' => new external_value(PARAM_INT, 'The option value'),
            'scaleconfiguration' => new external_multiple_structure(
                new external_single_structure(array ('id' => new external_value(PARAM_INT, 'The option value'),
                    'name' => new external_value(PARAM_TEXT, 'The option value'),
                    'color' => new external_value(PARAM_TEXT, 'The option value'),
                    'proficient' => new external_value(PARAM_BOOL, 'The proficient indicator'))
                ))
            ));
    }

    /**
     * Returns description of update_report_competency_config() parameters.
     *
     * @return \external_function_parameters
     */
    public static function update_report_competency_config_parameters() {
        $competencyframeworkid = new external_value(
            PARAM_INT,
            'The competency framework id',
            VALUE_REQUIRED
        );
        $scaleid = new external_value(
            PARAM_INT,
            'The scale id',
            VALUE_REQUIRED
        );
        $scaleconfiguration = new external_value(
            PARAM_RAW,
            'The scaleconfiguration',
            VALUE_REQUIRED
        );

        $params = array(
            'competencyframeworkid' => $competencyframeworkid,
            'scaleid' => $scaleid,
            'scaleconfiguration' => $scaleconfiguration
        );

        return new external_function_parameters($params);
    }

    /**
     * Update an existing configuration for a framework and a scale.
     *
     * @param int $competencyframeworkid Framework id.
     * @param int $scaleid Scale id.
     * @param string $scaleconfiguration Scale configuration.
     * @return boolean
     */
    public static function update_report_competency_config($competencyframeworkid, $scaleid, $scaleconfiguration) {

        $params = self::validate_parameters(self::update_report_competency_config_parameters(), array(
            'competencyframeworkid' => $competencyframeworkid,
            'scaleid' => $scaleid,
            'scaleconfiguration' => $scaleconfiguration
        ));

        $params = (object) $params;

        return api::update_report_competency_config($params);
    }

    /**
     * Returns description of uupdate_report_competency_config() result value.
     *
     * @return \external_description
     */
    public static function update_report_competency_config_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful');
    }

    /**
     * Returns description of read_plan() parameters.
     *
     * @return \external_function_parameters
     */
    public static function read_plan_parameters() {
        return new external_function_parameters(array(
            'planid' => new external_value(PARAM_INT, 'The plan ID'),
            'templateid' => new external_value(PARAM_INT, 'The template ID'),
            'scalevalues' => new external_value(PARAM_TEXT, 'The scale values filter'),
            'scalefilterbycourse' => new external_value(PARAM_INT, 'Apply the scale filters on grade in course'),
            'scalesortorder' => new external_value(PARAM_TEXT, 'Scale sort order', VALUE_DEFAULT, 'ASC'),
            'tagid' => new external_value(PARAM_INT, 'The tag ID')
        ));
    }

    /**
     * Get the plan information by plan ID or
     * template ID (first user returned from the list of plans).
     *
     * @param int $planid The plan ID
     * @param int $templateid The template ID
     * @param string $scalevalues The scales values filter
     * @param int $scalefilterbycourse Apply the scale filters on grade in course
     * @param string $scalesortorder Scale sort order
     * @param int $tagid The tag ID
     * @return array
     */
    public static function read_plan($planid, $templateid, $scalevalues = '', $scalefilterbycourse = true,
            $scalesortorder= 'ASC', $tagid = null) {
        global $PAGE;

        $context = context_system::instance();
        self::validate_context($context);

        $params = self::validate_parameters(self::read_plan_parameters(), array(
                    'planid' => $planid,
                    'templateid' => $templateid,
                    'scalevalues' => $scalevalues,
                    'scalefilterbycourse' => $scalefilterbycourse,
                    'scalesortorder' => $scalesortorder,
                    'tagid' => $tagid
                ));

        $plans = api::read_plan($params['planid'], $params['templateid'],
                json_decode($params['scalevalues'], true), $params['scalefilterbycourse'],
                $params['scalesortorder'], $params['tagid']);
        self::validate_context($plans->current->get_context());

        $output = $PAGE->get_renderer('report_lpmonitoring');

        $planexport = new \stdClass();
        $planexport->id = $plans->current->get('id');
        $planexport->name = $plans->current->get('name');

        $status = $plans->current->get('status');
        $planexport->isactive = $status == plan::STATUS_ACTIVE;
        $planexport->isdraft = $status == plan::STATUS_DRAFT;
        $planexport->iscompleted = $status == plan::STATUS_COMPLETE;
        $planexport->iswaitingforreview = $status == plan::STATUS_WAITING_FOR_REVIEW;
        $planexport->isinreview = $status == plan::STATUS_IN_REVIEW;
        $planexport->statusname = $plans->current->get_statusname();
        $planexport->usercontext = $plans->current->get_context()->id;
        $planexport->canmanage = $plans->current->can_manage();
        $planexport->cangrade = user_competency::can_grade_user($plans->current->get('userid'));
        $planexport->displayrating = \tool_lp\api::has_to_display_rating_for_plan($planexport->id);
        $planexport->canresetdisplayrating = \tool_lp\api::can_reset_display_rating_for_plan($planexport->id);
        // Set learning plan url.
        $planexport->url = url::plan($plans->current->get('id'))->out(false);
        // Get stats for plan.
        $uc = new \stdClass();
        $uc->usercompetencies = core_competency_api::list_plan_competencies($planexport->id);
        $statsexporter = new stats_plan_exporter($uc, ['plan' => $plans->current]);
        $planexport->stats = $statsexporter->export($output);

        $hasnavigation = false;

        $userexporter = new user_summary_exporter(core_user::get_user($plans->current->get('userid'), '*', \MUST_EXIST));
        $planexport->user = $userexporter->export($output);

        if (isset($plans->previous) || isset($plans->next)) {
            $hasnavigation = true;
        }

        $result = array(
            'plan' => $planexport,
            'hasnavigation' => $hasnavigation
        );

        foreach ($plans->fullnavigation as $key => $plan) {
            $plan = (object) $plan;
            $profileimage = $plan->profileimage;
            $profileimage->size = 0;
            $plan->profileimage = $profileimage->get_url($PAGE)->out(false);
            $plan->current = $plan->planid == $plans->current->get('id') ? true : false;
            if (!empty($params['tagid'])) {
                $plan->tagid = $params['tagid'];
            }
            $plans->fullnavigation[$key] = $plan;
        }
        $result['fullnavigation'] = $plans->fullnavigation;

        if (isset($plans->previous)) {
            $profileimage = $plans->previous->profileimage;
            $plans->previous->profileimage = $profileimage->get_url($PAGE)->out(false);
            $profileimage->size = 0;
            $plans->previous->profileimagesmall  = $profileimage->get_url($PAGE)->out(false);
            $result['navprev'] = $plans->previous;
        }
        if (isset($plans->next)) {
            $profileimage = $plans->next->profileimage;
            $plans->next->profileimage = $profileimage->get_url($PAGE)->out(false);
            $profileimage->size = 0;
            $plans->next->profileimagesmall  = $profileimage->get_url($PAGE)->out(false);
            $result['navnext'] = $plans->next;
        }

        return external_api::clean_returnvalue(self::read_plan_returns(), $result);
    }

    /**
     * Returns description of read_plan() result value.
     *
     * @return \external_description
     */
    public static function read_plan_returns() {
        $plan = new external_single_structure(array(
            'id'   => new external_value(PARAM_INT, 'The plan ID'),
            'name' => new external_value(PARAM_TEXT, 'The plan name'),
            'user' => user_summary_exporter::get_read_structure(),
            'usercontext' => new external_value(PARAM_INT, 'The user context ID value'),
            'isactive' => new external_value(PARAM_BOOL, 'Is plan active'),
            'canmanage' => new external_value(PARAM_BOOL, 'Can manage user plan'),
            'cangrade' => new external_value(PARAM_BOOL, 'Can grade user plan'),
            'displayrating' => new external_value(PARAM_BOOL, 'Is ratings displayed for user'),
            'canresetdisplayrating' => new external_value(PARAM_BOOL, 'Plan has a display rating setting'),
            'isdraft' => new external_value(PARAM_BOOL, 'Is plan draft'),
            'iscompleted' => new external_value(PARAM_BOOL, 'Is plan completed'),
            'iswaitingforreview' => new external_value(PARAM_BOOL, 'Is plan completed'),
            'isinreview' => new external_value(PARAM_BOOL, 'Is plan completed'),
            'statusname' => new external_value(PARAM_TEXT, 'The plan status name'),
            'url' => new external_value(PARAM_TEXT, 'The plan url'),
            'stats' => stats_plan_exporter::get_read_structure()
        ), 'The plan information');

        $usernav = new external_single_structure(array(
            'fullname' => new external_value(PARAM_TEXT, 'The fullname of the user'),
            'profileimage' => new external_value(PARAM_TEXT, 'The profile image small size'),
            'profileimagesmall' => new external_value(PARAM_TEXT, 'The profile image small size'),
            'userid' => new external_value(PARAM_INT, 'The user ID value'),
            'planid' => new external_value(PARAM_INT, 'The plan ID value'),
            'tagid' => new external_value(PARAM_INT, 'The tag ID value', VALUE_OPTIONAL)
        ), 'The user and plan ID navigation information', VALUE_OPTIONAL);

        $fullnavigation = new external_multiple_structure(
            new external_single_structure(array(
                'fullname' => new external_value(PARAM_TEXT, 'The fullname of the user'),
                'planname' => new external_value(PARAM_TEXT, 'The plan name'),
                'email' => new external_value(PARAM_TEXT, 'The email of the user', VALUE_OPTIONAL),
                'idnumber' => new external_value(PARAM_TEXT, 'The idnumber of the user', VALUE_OPTIONAL),
                'profileimage' => new external_value(PARAM_TEXT, 'The profile image small size'),
                'userid' => new external_value(PARAM_INT, 'The user ID value'),
                'planid' => new external_value(PARAM_INT, 'The plan ID value'),
                'nbrating' => new external_value(PARAM_INT, 'The nb rating value', VALUE_OPTIONAL),
                'current' => new external_value(PARAM_BOOL, 'Is current user'),
                'tagid' => new external_value(PARAM_INT, 'The tag ID value', VALUE_OPTIONAL)
            ), 'Full navigation list', VALUE_OPTIONAL)
        );

        return new external_single_structure(array(
            'plan' => $plan,
            'hasnavigation' => new external_value(PARAM_BOOL, 'Has navigation returned for previous and/or next plans'),
            'navprev' => $usernav,
            'navnext' => $usernav,
            'fullnavigation' => $fullnavigation
        ));
    }

    /**
     * Returns description of get_competency_detail.
     *
     * @return \external_function_parameters
     */
    public static function get_competency_detail_parameters() {
        $userid = new external_value(
            PARAM_INT,
            'The user id',
            VALUE_REQUIRED
        );

        $competencyid = new external_value(
            PARAM_INT,
            'The competency id',
            VALUE_REQUIRED
        );

        $planid = new external_value(
            PARAM_INT,
            'The plan id',
            VALUE_REQUIRED
        );

        $params = array(
            'userid' => $userid,
            'competencyid' => $competencyid,
            'planid' => $planid
        );
        return new external_function_parameters($params);
    }

    /**
     * Returns the competency detail for lp_monitoring report.
     *
     * @param int $userid User id.
     * @param int $competencyid Competency id.
     * @param int $planid Plan id.
     *
     * @return array
     */
    public static function get_competency_detail($userid, $competencyid, $planid) {
        global $PAGE;

        $params = self::validate_parameters(self::get_competency_detail_parameters(), array(
            'userid' => $userid,
            'competencyid' => $competencyid,
            'planid' => $planid
        ));
        $context = context_system::instance();
        self::validate_context($context);

        $result = api::get_competency_detail($params['userid'], $params['competencyid'], $params['planid']);
        $result->displayrating = \tool_lp\api::has_to_display_rating($params['planid']);

        $output = $PAGE->get_renderer('report_lpmonitoring');
        $exporter = new lpmonitoring_competency_detail_exporter($result);
        $record = $exporter->export($output);

        return $record;
    }

    /**
     * Returns description of get_competency_detail() result value.
     *
     * @return \external_description
     */
    public static function get_competency_detail_returns() {
        return lpmonitoring_competency_detail_exporter::get_read_structure();
    }

    /**
     * External function parameters structure.
     *
     * @return \external_description
     */
    public static function list_plan_competencies_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'The plan ID.')
        ));
    }

    /**
     * List plan competencies.
     * @param  int $id The plan ID.
     * @return array
     */
    public static function list_plan_competencies($id) {

        $plan = \core_competency\api::read_plan($id);
        $result = core_competency_external::list_plan_competencies($id);
        $displayrating = true;
        if ($plan->get('status') == \core_competency\plan::STATUS_ACTIVE) {
            $displayrating = \tool_lp\api::has_to_display_rating($plan);
        }
        foreach ($result as $key => $r) {
            $usercompetency = (isset($r->usercompetency)) ? $r->usercompetency : $r->usercompetencyplan;
            $proficiency = $usercompetency->proficiency;
            $r->isnotrated = false;
            $r->isproficient = false;
            $r->isnotproficient = false;
            if (!$displayrating) {
                $r->isnotrated = true;
                continue;
            }
            if (!isset($proficiency)) {
                $r->isnotrated = true;
            } else {
                if ($proficiency) {
                    $r->isproficient = true;
                } else {
                    $r->isnotproficient = true;
                }
            }
        }
        return $result;
    }

    /**
     * External function return structure.
     *
     * @return \external_description
     */
    public static function list_plan_competencies_returns() {
        $uc = user_competency_exporter::get_read_structure();
        $ucp = user_competency_plan_exporter::get_read_structure();

        $uc->required = VALUE_OPTIONAL;
        $ucp->required = VALUE_OPTIONAL;

        return new external_multiple_structure(
            new external_single_structure(array(
                'competency' => competency_exporter::get_read_structure(),
                'usercompetency' => $uc,
                'usercompetencyplan' => $ucp,
                'isproficient' => new external_value(PARAM_BOOL, 'True if the competency is proficient'),
                'isnotproficient' => new external_value(PARAM_BOOL, 'False if the competency is proficient'),
                'isnotrated' => new external_value(PARAM_BOOL, 'True if the competency is not rated'),
            ))
        );
    }

    /**
     * Returns description of get_competency_statistics.
     *
     * @return \external_function_parameters
     */
    public static function get_competency_statistics_parameters() {

        $competencyid = new external_value(
            PARAM_INT,
            'The competency id',
            VALUE_REQUIRED
        );

        $templateid = new external_value(
            PARAM_INT,
            'The template id',
            VALUE_REQUIRED
        );

        $params = array(
            'competencyid' => $competencyid,
            'templateid' => $templateid
        );
        return new external_function_parameters($params);
    }

    /**
     * Returns the competency statistics for lp_monitoring report.
     *
     * @param int $competencyid Competency id.
     * @param int $templateid Template id.
     * @return array
     */
    public static function get_competency_statistics($competencyid, $templateid) {
        global $PAGE;

        $params = self::validate_parameters(self::get_competency_statistics_parameters(), array(
            'competencyid' => $competencyid,
            'templateid' => $templateid
        ));
        $context = context_system::instance();
        self::validate_context($context);

        $result = api::get_competency_statistics($params['competencyid'], $params['templateid']);

        $output = $PAGE->get_renderer('report_lpmonitoring');
        $exporter = new lpmonitoring_competency_statistics_exporter($result);
        $record = $exporter->export($output);

        return $record;
    }

    /**
     * Returns description of get_competency_statistics() result value.
     *
     * @return \external_description
     */
    public static function get_competency_statistics_returns() {
        return lpmonitoring_competency_statistics_exporter::get_read_structure();
    }

    /**
     * Returns description of get_competency_statistics_incourse.
     *
     * @return \external_function_parameters
     */
    public static function get_competency_statistics_incourse_parameters() {

        $competencyid = new external_value(
            PARAM_INT,
            'The competency id',
            VALUE_REQUIRED
        );

        $templateid = new external_value(
            PARAM_INT,
            'The template id',
            VALUE_REQUIRED
        );

        $params = array(
            'competencyid' => $competencyid,
            'templateid' => $templateid
        );
        return new external_function_parameters($params);
    }

    /**
     * Returns the competency statistics in course.
     *
     * @param int $competencyid Competency id.
     * @param int $templateid Template id.
     * @return array
     */
    public static function get_competency_statistics_incourse($competencyid, $templateid) {
        global $PAGE;

        $params = self::validate_parameters(self::get_competency_statistics_incourse_parameters(), array(
            'competencyid' => $competencyid,
            'templateid' => $templateid
        ));
        $context = context_system::instance();
        self::validate_context($context);

        $result = api::get_competency_statistics_in_course($params['competencyid'], $params['templateid']);

        $output = $PAGE->get_renderer('report_lpmonitoring');
        $exporter = new lpmonitoring_competency_statistics_incourse_exporter($result);
        $record = $exporter->export($output);

        return $record;
    }

    /**
     * Returns description of get_competency_statistics() result value.
     *
     * @return \external_description
     */
    public static function get_competency_statistics_incourse_returns() {
        return lpmonitoring_competency_statistics_incourse_exporter::get_read_structure();
    }

    /**
     * Describes the parameters for submit_manage_tags_form webservice.
     * @return external_function_parameters
     */
    public static function submit_manage_tags_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the manage tags form, encoded as a json array')
            )
        );
    }

    /**
     * Submit the manage tags form.
     *
     * @param int $contextid The context id for the user.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return int the new number of tags associated to the learning plan.
     */
    public static function submit_manage_tags_form($contextid, $jsonformdata) {
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_manage_tags_form_parameters(),
                                            ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);

        $serialiseddata = json_decode($params['jsonformdata']);

        $data = array();
        parse_str($serialiseddata, $data);

        // The last param is the ajax submitted data.
        $mform = new \report_lpmonitoring\form\tags(null, array('planid' => $data['planid']), 'post', '', null, true, $data);

        $validateddata = $mform->get_data();

        if ($validateddata) {
            // Save the tags.
            core_tag_tag::set_item_tags('report_lpmonitoring', 'competency_plan',
                                        $validateddata->planid, $context, $validateddata->tags);
        } else {
            // Generate a warning.
            throw new moodle_exception('errormanagetags', 'report_lpmonitoring');
        }

        return count(core_tag_tag::get_item_tags('report_lpmonitoring', 'competency_plan', $validateddata->planid));
    }

    /**
     * Returns description of submit_manage_tags_form() result value.
     *
     * @return \external_description
     */
    public static function submit_manage_tags_form_returns() {
        return new external_value(PARAM_INT, 'The number of tags associated to the learning plan');
    }

    /**
     * Describes the parameters for search_plans_with_tag webservice.
     * @return external_function_parameters
     */
    public static function search_plans_with_tag_parameters() {
        return new external_function_parameters(
            array(
                'tagid' => new external_value(PARAM_INT, 'The tag ID')
            )
        );
    }

    /**
     * Get the plans with a specific tag (but only plans that the user can view).
     *
     * @param int $tagid The tag id.
     * @return array
     */
    public static function search_plans_with_tag($tagid) {
        global $PAGE;

        $context = context_system::instance();
        self::validate_context($context);

        $plans = api::search_plans_with_tag($tagid);
        foreach ($plans as $index => $plan) {
            // Return profileimage as url instead of object.
            $plans[$index]['profileimage'] = $plans[$index]['profileimage']->get_url($PAGE)->out(false);
            $plans[$index]['profileimagesmall'] = $plans[$index]['profileimage'];
        }
        return (array) (object) $plans;
    }

    /**
     * Returns description of search_plans_with_tag() result value.
     *
     * @return \external_description
     */
    public static function search_plans_with_tag_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'fullname' => new external_value(PARAM_TEXT, 'The fullname of the user'),
                'profileimage' => new external_value(PARAM_TEXT, 'The profile image small size'),
                'profileimagesmall' => new external_value(PARAM_TEXT, 'The profile image small size'),
                'userid' => new external_value(PARAM_INT, 'The user id value'),
                'planid' => new external_value(PARAM_INT, 'The plan id value'),
                'planname' => new external_value(PARAM_TEXT, 'The name of the learning plan template')
            ))
        );
    }

    /**
     * Describes the parameters for search_tags_for_accessible_plans webservice.
     * @return external_function_parameters
     */
    public static function search_tags_for_accessible_plans_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Get the plans with a specific tag (but only plans that the user can view).
     *
     * @return array
     */
    public static function search_tags_for_accessible_plans() {
        $tags = api::search_tags_for_accessible_plans();
        $return = array();
        foreach ($tags as $tagid => $tag) {
            $return[] = array('id' => $tagid, 'tag' => $tag);
        }
        return $return;
    }

    /**
     * Returns description of search_tags_for_accessible_plans() result value.
     *
     * @return \external_description
     */
    public static function search_tags_for_accessible_plans_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(PARAM_INT, 'The tag ID'),
                'tag' => new external_value(PARAM_TEXT, 'The tag')
            ))
        );
    }

    /**
     * Returns description of get_comment_area_for_plan() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_comment_area_for_plan_parameters() {
        $planid = new external_value(
            PARAM_INT,
            'The plan id',
            VALUE_REQUIRED
        );
        $params = array('planid' => $planid);
        return new external_function_parameters($params);
    }

    /**
     * Loads the data required to render a comment area of a learning plan.
     *
     * @param int $planid Learning Plan id.
     * @return stdClass
     */
    public static function get_comment_area_for_plan($planid) {
        global $PAGE;
        $params = self::validate_parameters(self::get_comment_area_for_plan_parameters(), array(
            'planid' => $planid
        ));

        $plan = new plan($params['planid']);
        self::validate_context($plan->get_context());

        $output = $PAGE->get_renderer('core');
        $commentareaexporter = new comment_area_exporter($plan->get_comment_object());
        return $commentareaexporter->export($output);
    }

    /**
     * Returns description of get_comment_area_for_plan() result value.
     *
     * @return \external_description
     */
    public static function get_comment_area_for_plan_returns() {
        return comment_area_exporter::get_read_structure();
    }

    /**
     * Returns description of list_plan_competencies_report() parameters.
     *
     * @return \external_description
     */
    public static function list_plan_competencies_report_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'The plan ID.')
        ));
    }

    /**
     * List plan competencies for the report.
     * @param  int $id The plan ID.
     * @return array
     */
    public static function list_plan_competencies_report($id) {
        return null;
    }

    /**
     * Returns description of list_plan_competencies_report() result value.
     *
     * @return \external_description
     */
    public static function list_plan_competencies_report_returns() {
        return null;
    }
}
