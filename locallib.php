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
 * Internal library of functions for module newsletter
 *
 * @package    mod_newsletter
 * @copyright  2013 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/renderable.php');
require_once(dirname(__FILE__).'/CssToInlineStyles/CssToInlineStyles.php');

class newsletter implements renderable {

    /** @var stdClass the newsletter record that contains the global settings for this newsletter instance */
    private $instance = null;

    /** @var context the context of the course module for this newsletter instance (or just the course if we are
     creating a new one) */
    private $context = null;

    /** @var stdClass the course this newsletter instance belongs to */
    private $course = null;

    /** @var stdClass the course module for this assign instance */
    private $coursemodule = null;

    /** @var stdClass the admin config for all newsletter instances  */
    private $config = null;

    /** @var mod_newsletter_renderer the custom renderer for this module */
    private $renderer = null;

    public static function get_newsletter_by_instance($instanceid, $eagerload = false) {
        $cm = get_coursemodule_from_instance('newsletter', $instanceid);
        return new newsletter($cm->id, $eagerload);
    }

    public static function get_newsletter_by_course_module($cmid, $eagerload = false) {
        return new newsletter($cmid, $eagerload);
    }

    public function __construct($cmid, $eagerload = false) {
        $this->context = context_module::instance($cmid);
        if ($eagerload) {
            global $DB, $PAGE;
            $this->coursemodule = get_coursemodule_from_id('newsletter', $cmid, 0, false, MUST_EXIST);
            $this->course = $DB->get_record('course', array('id' => $this->coursemodule->course), '*', MUST_EXIST);
            $this->instance = $DB->get_record('newsletter', array('id' => $this->get_course_module()->instance), '*', MUST_EXIST);
            $this->config = get_config('newsletter');
            $this->renderer = $PAGE->get_renderer('mod_newsletter');
        }
    }

    /**
     * Get context module
     *
     * @return context
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get the current course module
     *
     * @return mixed stdClass|null The course module
     */
    public function get_course_module() {
        if (!$this->coursemodule) {
            if ($this->context && $this->context->contextlevel == CONTEXT_MODULE) {
                $this->coursemodule = get_coursemodule_from_id('newsletter', $this->context->instanceid, 0, false, MUST_EXIST);
            }
        }
        return $this->coursemodule;
    }

    /**
     * Get the settings for the current instance of this newsletter.
     *
     * @return stdClass The settings
     */
    public function get_instance() {
        if (!$this->instance) {
            if ($this->get_course_module()) {
                global $DB;
                $this->instance = $DB->get_record('newsletter', array('id' => $this->get_course_module()->instance), '*', MUST_EXIST);
            } else {
                throw new coding_exception('Improper use of the newsletter class. Cannot load the newsletter record.');
            }
        }
        return $this->instance;
    }

    /**
     * Get the current course
     *
     * @return mixed stdClass|null The course
     */
    public function get_course() {
        global $DB;
        if (!$this->course) {
            if ($this->context) {
                $this->course = $DB->get_record('course', array('id' => $this->get_instance()->course), '*', MUST_EXIST);
            }
        }
        return $this->course;
    }
    /**
     * Get the module renderer
     *
     * @return mixed stdClass|null The module renderer
     */
    public function get_renderer() {
        if (!$this->renderer) {
            global $PAGE;
            $this->renderer = $PAGE->get_renderer('mod_newsletter');
        }
        return $this->renderer;
    }

    public function get_config() {
        if (!$this->config) {
            $this->config = get_config('newsletter');
        }
        return $this->config;
    }

    public function reset_userdata($data) {
        global $CFG, $DB;

        $newsletterssql = "SELECT n.id
                             FROM {newsletter} n
                            WHERE n.course = :course";
        $params = array("course" => $data->courseid);

        $DB->delete_records_select('newsletter_submissions', "newsletter IN ($newsletterssql)", $params);
        $status[] = array('component' => get_string('modulenameplural', 'newsletter'),
                          'item' => get_string('delete_all_subscriptions','newsletter'),
                          'error' => false);

        return array();
    }

    private function get_js_module($strings = array()) {
        $jsmodule = array(
            'name' => 'mod_newsletter',
            'fullpath' => '/mod/newsletter/module.js',
            'requires' => array('node', 'event', 'node-screen', 'panel', 'node-event-delegate'),
            'strings' => $strings,
            );

        return $jsmodule;
    }

    /**
     * Render the view according to passed parameters
     *
     * @return string rendered view
     */
    public function view($params) {
        switch ($params[NEWSLETTER_PARAM_ACTION]) {
        case NEWSLETTER_ACTION_VIEW_NEWSLETTER:
            require_capability('mod/newsletter:viewnewsletter', $this->context);
            $output = $this->view_newsletter($params);
            break;
        case NEWSLETTER_ACTION_CREATE_ISSUE:
            require_capability('mod/newsletter:createissue', $this->context);
            $output = $this->view_edit_issue_page($params);
            break;
        case NEWSLETTER_ACTION_EDIT_ISSUE:
            require_capability('mod/newsletter:editissue', $this->context);
            $output = $this->view_edit_issue_page($params);
            break;
        case NEWSLETTER_ACTION_READ_ISSUE:
            require_capability('mod/newsletter:readissue', $this->context);
            $output = $this->view_read_issue_page($params);
            break;
        case NEWSLETTER_ACTION_DELETE_ISSUE:
            require_capability('mod/newsletter:deleteissue', $this->context);
            $output = $this->view_delete_issue_page($params);
            break;
        case NEWSLETTER_ACTION_MANAGE_SUBSCRIPTIONS:
            require_capability('mod/newsletter:managesubscriptions', $this->context);
            $output = $this->view_manage_subscriptions($params);
            break;
        case NEWSLETTER_ACTION_EDIT_SUBSCRIPTION:
            require_capability('mod/newsletter:editsubscription', $this->context);
            $output = $this->view_edit_subscription($params);
            break;
        case NEWSLETTER_ACTION_DELETE_SUBSCRIPTION:
            require_capability('mod/newsletter:deletesubscription', $this->context);
            $output = $this->view_delete_subscription($params);
            break;
        case NEWSLETTER_ACTION_SUBSCRIBE:
            require_capability('mod/newsletter:manageownsubscription', $this->context);
            $this->subscribe();
            $url = new moodle_url('/mod/newsletter/view.php', array('id' => $this->get_course_module()->id));
            redirect($url);
            break;
        case NEWSLETTER_ACTION_UNSUBSCRIBE:
            require_capability('mod/newsletter:manageownsubscription', $this->context);
            $this->unsubscribe();
            $url = new moodle_url('/mod/newsletter/view.php', array('id' => $this->get_course_module()->id));
            redirect($url);
            break;
        default:
            print_error('Wrong ' . NEWSLETTER_PARAM_ACTION . ' parameter value: ' . $params[NEWSLETTER_PARAM_ACTION]);
            break;
        }

        return $output;
    }

    private function view_newsletter(array $params) {
        $renderer = $this->get_renderer();
        require_once(dirname(__FILE__).'/guest_signup_form.php');
        $mform = new mod_newsletter_guest_signup_form(null, array('id' => $this->get_course_module()->id));

        if ($data = $mform->get_data()) {
            $this->subscribe_guest($data->firstname, $data->lastname, $data->email);
            $url = new moodle_url('/mod/newsletter/view.php', array('id' => $this->get_course_module()->id));
            redirect($url);
        }

        global $PAGE, $USER;
        $PAGE->requires->js_module($this->get_js_module());
        $PAGE->requires->js_init_call('M.mod_newsletter.collapse_subscribe_form');

        $output = '';
        $output .= $renderer->render(
                new newsletter_header(
                        $this->get_instance(),
                        $this->get_context(),
                        false,
                        $this->get_course_module()->id));
        $output .= $renderer->render(new newsletter_main_toolbar(
                                $this->get_course_module()->id,
                                $params[NEWSLETTER_PARAM_GROUP_BY],
                                has_capability('mod/newsletter:createissue', $this->context),
                                has_capability('mod/newsletter:managesubscriptions', $this->context)));
        $issuelist = $this->prepare_issue_list('', $params[NEWSLETTER_PARAM_GROUP_BY]);
        if ($issuelist) {
            $output .= $renderer->render($issuelist);
        } else {
            $output .= '<h2>' . get_string('no_issues', 'newsletter') . '</h2>';
        }
        if (has_capability('mod/newsletter:manageownsubscription', $this->context)) {
            if (!$this->is_subscribed()) {
                $url = new moodle_url('/mod/newsletter/view.php',
                                array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id,
                                      NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_SUBSCRIBE));
                $text = get_string('subscribe', 'newsletter');
                $output .= html_writer::link($url, $text);
            } else {
                $url = new moodle_url('/mod/newsletter/view.php',
                                array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id,
                                      NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_UNSUBSCRIBE));
                $text = get_string('unsubscribe', 'newsletter');
                $output .= html_writer::link($url, $text);
            }
        } else {
            if ($this->get_config()->allow_guest_user_subscriptions) {
                $output .= $renderer->render(new newsletter_form($mform, null));
            }
        }
        $output .= $renderer->render_footer();
        return $output;
    }

    private function view_read_issue_page(array $params) {
        global $CFG;
        $renderer = $this->get_renderer();

        $output = $renderer->render(
                new newsletter_header(
                        $this->get_instance(),
                        $this->get_context(),
                        false,
                        $this->get_course_module()->id));
        $currentissue = $this->get_issue($params[NEWSLETTER_PARAM_ISSUE]);
        $navigation_bar = new newsletter_navigation_bar(
                                $currentissue,
                                $this->get_first_issue($currentissue),
                                $this->get_previous_issue($currentissue),
                                $this->get_next_issue($currentissue),
                                $this->get_last_issue($currentissue));
        $output .= $renderer->render($navigation_bar);

        $currentissue->htmlcontent = $this->inline_css($currentissue->htmlcontent, $currentissue->stylesheetid);

        $output .= $renderer->render(new newsletter_issue($currentissue));

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->get_context()->id, 'mod_newsletter', 'attachments', $currentissue->id, "", false);
        foreach ($files as $file) {
            $file->link = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->get_context()->id.'/mod_newsletter/attachments/'.$currentissue->id.'/'.$file->get_filename());
        }

        $output .= $renderer->render(new newsletter_attachment_list($files));
        $output .= $renderer->render($navigation_bar);
        $output .= $renderer->render_footer();
        return $output;
    }

    private function view_delete_issue_page(array $params) {
        if (!$params[NEWSLETTER_PARAM_ISSUE] || !$this->check_issue_id($params[NEWSLETTER_PARAM_ISSUE])) {
            print_error('Wrong ' . NEWSLETTER_PARAM_ISSUE . ' parameter value: ' . $params[NEWSLETTER_PARAM_ISSUE]);
        }

        if ($params[NEWSLETTER_PARAM_CONFIRM] != NEWSLETTER_CONFIRM_UNKNOWN) {
            $url = new moodle_url('/mod/newsletter/view.php', array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id));
            if ($params[NEWSLETTER_PARAM_CONFIRM] == NEWSLETTER_CONFIRM_YES) {
                $this->delete_issue($params[NEWSLETTER_PARAM_ISSUE]);
                redirect($url);
            } else if ($params[NEWSLETTER_PARAM_CONFIRM] == NEWSLETTER_CONFIRM_NO) {
                redirect($url);
            } else {
                print_error("Wrong confirm!");
            }
        }

        $renderer = $this->get_renderer();
        $output = '';
        $output .= $renderer->render(
                    new newsletter_header(
                                $this->get_instance(),
                                $this->get_context(),
                                false,
                                $this->get_course_module()->id));
        $url = new moodle_url('/mod/newsletter/view.php',
                              array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id,
                                    NEWSLETTER_PARAM_ISSUE => $params[NEWSLETTER_PARAM_ISSUE],
                                    NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_DELETE_ISSUE));
        $output .=  $OUTPUT->confirm(get_string('delete_issue_question', 'newsletter'),
                new moodle_url($url, array(NEWSLETTER_PARAM_CONFIRM => NEWSLETTER_CONFIRM_YES)),
                new moodle_url($url, array(NEWSLETTER_PARAM_CONFIRM => NEWSLETTER_CONFIRM_NO)));
        $output .= $renderer->render_footer();
        return $output;
    }

    private function view_edit_issue_page(array $params) {
        global $CFG;
        if (!$this->check_issue_id($params[NEWSLETTER_PARAM_ISSUE])) {
            print_error('Wrong ' . NEWSLETTER_PARAM_ISSUE . ' parameter value: ' . $params[NEWSLETTER_PARAM_ISSUE]);
        }

        $issue = $this->get_issue($params[NEWSLETTER_PARAM_ISSUE]);

        $fs = get_file_storage();
        $context = $this->get_context();
        $files = $fs->get_area_files($context->id, 'mod_newsletter', NEWSLETTER_FILE_AREA_STYLESHEETS, $this->get_instance()->id, 'filename', false);
        $options = array();
        $options[NEWSLETTER_DEFAULT_STYLESHEET] = "{$CFG->wwwroot}/mod/newsletter/reset.css";
        foreach ($files as $file) {
            $url = "{$CFG->wwwroot}/pluginfile.php/{$file->get_contextid()}/mod_newsletter/" . NEWSLETTER_FILE_AREA_STYLESHEETS;
            $options[$file->get_id()] = $url . $file->get_filepath() . $file->get_itemid() . '/' . $file->get_filename();
        }

        global $PAGE;
        $PAGE->requires->js_module($this->get_js_module());
        $PAGE->requires->js_init_call('M.mod_newsletter.init_tinymce', array($options, $issue ? $issue->stylesheetid : NEWSLETTER_DEFAULT_STYLESHEET));

        require_once(dirname(__FILE__).'/issue_form.php');
        $mform = new mod_newsletter_issue_form(null, array(
                'newsletter' => $this,
                'issue' => $issue));

        if ($data = $mform->get_data()) {
            if (!$data->issue) {
                $this->add_issue($data);
            } else {
                $this->update_issue($data);
            }
            $url = new moodle_url('/mod/newsletter/view.php', array('id' => $this->get_course_module()->id));
            redirect($url);
        }

        $output = '';
        $renderer = $this->get_renderer();

        $output .= $renderer->render(
                    new newsletter_header(
                                $this->get_instance(),
                                $this->get_context(),
                                false,
                                $this->get_course_module()->id));
        $output .= $renderer->render(new newsletter_form($mform, get_string('edit_issue_title', 'newsletter')));
        $output .= $renderer->render_footer();
        return $output;
    }

    private function view_manage_subscriptions(array $params) {
        global $DB;
        $allnamefields = user_picture::fields('u',null,'userid');
        $sql = "SELECT ns.id, ns.health, $allnamefields
                  FROM {newsletter_subscriptions} ns
            INNER JOIN {user} u ON ns.userid = u.id
                 WHERE ns.newsletterid = :newsletterid";
        $sqlparams = array('newsletterid' => $this->get_instance()->id);
        $from = $params[NEWSLETTER_PARAM_FROM];
        $count = $params[NEWSLETTER_PARAM_COUNT];
        $subscriptions = $DB->get_records_sql($sql, $sqlparams, $from, $count);

        $total = $DB->count_records('newsletter_subscriptions', $sqlparams);
        $pages = $this->calculate_pages($total, $from, $count);

        $columns = array(NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_EMAIL,
                         NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_NAME,
                         NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_HEALTH,
                         NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_ACTIONS);

        $renderer = $this->get_renderer();
        $output = '';
        $output .= $renderer->render(
                    new newsletter_header(
                                $this->get_instance(),
                                $this->get_context(),
                                false,
                                $this->get_course_module()->id));
        $url = new moodle_url('/mod/newsletter/view.php',
                              array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id,
                                    NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_MANAGE_SUBSCRIPTIONS));
        $output .= $renderer->render(new newsletter_pager($url, $from, $count, $pages));
        $output .= $renderer->render(new newsletter_subscription_list($this->get_course_module()->id, $subscriptions, $columns));

        require_once(dirname(__FILE__).'/subscriptions_admin_form.php');
        $mform = new mod_newsletter_subscriptions_admin_form(null, array(
                'id' => $this->get_course_module()->id,
                'course' => $this->get_course()));

        if ($data = $mform->get_data()) {
            if(isset($data->subscribe)) {
                foreach ($data->cohorts as $cohortid) {
                    $this->subscribe_cohort($cohortid);
                }
            } else if(isset($data->unsubscribe)) {
                foreach ($data->cohorts as $cohortid) {
                    $this->unsubscribe_cohort($cohortid);
                }
            } else {
                print_error("Wrong submit!");
            }
            $url = new moodle_url('/mod/newsletter/view.php', array(
                    'id' => $this->get_course_module()->id,
                    'action' => NEWSLETTER_ACTION_MANAGE_SUBSCRIPTIONS));
            redirect($url);
        }

        $output .= $renderer->render(new newsletter_form($mform, null));
        $output .= $renderer->render_footer();
        return $output;
    }

    private function view_edit_subscription(array $params) {
        global $DB;
        $subscription = $DB->get_record('newsletter_subscriptions', array('id' => $params[NEWSLETTER_PARAM_SUBSCRIPTION]));
        require_once(dirname(__FILE__).'/subscription_form.php');
        $mform = new mod_newsletter_subscription_form(null, array(
                'newsletter' => $this,
                'subscription' => $subscription));

        if ($data = $mform->get_data()) {
            $this->update_subscription($data);
            $url = new moodle_url('/mod/newsletter/view.php',
                    array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id,
                          NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_MANAGE_SUBSCRIPTIONS));
            redirect($url);
        }

        $output = '';
        $renderer = $this->get_renderer();

        $output .= $renderer->render(
                    new newsletter_header(
                                $this->get_instance(),
                                $this->get_context(),
                                false,
                                $this->get_course_module()->id));
        $output .= $renderer->render(new newsletter_form($mform, get_string('edit_subscription_title', 'newsletter')));
        $output .= $renderer->render_footer();
        return $output;
    }

    private function view_delete_subscription(array $params) {
        global $OUTPUT;

        if ($params[NEWSLETTER_PARAM_CONFIRM] != NEWSLETTER_CONFIRM_UNKNOWN) {
            $redirecturl = new moodle_url('/mod/newsletter/view.php',
                    array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id,
                          NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_MANAGE_SUBSCRIPTIONS));
            if ($params[NEWSLETTER_PARAM_CONFIRM] == NEWSLETTER_CONFIRM_YES) {
                $this->delete_subscription($params[NEWSLETTER_PARAM_SUBSCRIPTION]);
                redirect($redirecturl);
            } else if ($params[NEWSLETTER_PARAM_CONFIRM] == NEWSLETTER_CONFIRM_NO) {
                redirect($redirecturl);
            } else {
                print_error("Wrong confirm!");
            }
        }

        $renderer = $this->get_renderer();
        $output = $renderer->render(
                new newsletter_header(
                        $this->get_instance(),
                        $this->get_context(),
                        false,
                        $this->get_course_module()->id));

        $url = new moodle_url('/mod/newsletter/view.php',
                              array(NEWSLETTER_PARAM_ID => $this->get_course_module()->id,
                                    NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_DELETE_SUBSCRIPTION,
                                    NEWSLETTER_PARAM_SUBSCRIPTION => $params[NEWSLETTER_PARAM_SUBSCRIPTION]));
        $output .=  $OUTPUT->confirm(get_string('delete_subscription_question', 'newsletter'),
                new moodle_url($url, array(NEWSLETTER_PARAM_CONFIRM => NEWSLETTER_CONFIRM_YES)),
                new moodle_url($url, array(NEWSLETTER_PARAM_CONFIRM => NEWSLETTER_CONFIRM_NO)));
        $output .= $renderer->render_footer();
        return $output;
    }

    private function prepare_issue_list($heading, $groupby) {
        global $DB;
        // TODO: Add first day of the week check

        $editissue = has_capability('mod/newsletter:editissue', $this->get_context());
        $deleteissue = has_capability('mod/newsletter:deleteissue', $this->get_context());

        $issues = $this->get_issues();
        if (empty($issues)) {
            return null;
        }
        $firstissue = reset($issues);
        $firstdayofweek = (int) get_string('firstdayofweek', 'langconfig');
        switch ($groupby) {
        case NEWSLETTER_GROUP_ISSUES_BY_YEAR:
            $from = strtotime("first day of this year", $firstissue->publishon);
            $to = strtotime("next year", $from);
            $dateformat = "%Y";
            break;
        case NEWSLETTER_GROUP_ISSUES_BY_MONTH:
            $from = strtotime("first day of this month", $firstissue->publishon);
            $to = strtotime("next month", $from);
            $dateformat = "%B %Y";
            break;
        case NEWSLETTER_GROUP_ISSUES_BY_WEEK:
            $from = strtotime("Monday this week", $firstissue->publishon);
            $to = strtotime("Monday next week", $from);
            $dateformat = "Week %W of year %Y";
            $datefromto = "%d. %B %Y";
            break;
        }

        $sectionlist = new newsletter_section_list($heading);
        $currentissuelist = new newsletter_issue_summary_list();
        foreach ($issues as $issue) {
            while ($issue->publishon < $from) {
                $from = $to;
                switch ($groupby) {
                case NEWSLETTER_GROUP_ISSUES_BY_YEAR:
                    $to = strtotime("next year", $from);
                    break;
                case NEWSLETTER_GROUP_ISSUES_BY_MONTH:
                    $to = strtotime("next month", $from);
                    break;
                case NEWSLETTER_GROUP_ISSUES_BY_WEEK:
                    $to = strtotime("Monday next week", $from);
                    break;
                }
            }

            if ($issue->publishon < $to) {
                $currentissuelist->add_issue_summary(new newsletter_issue_summary($issue, $editissue, $deleteissue));
            } else {
                if ($groupby == NEWSLETTER_GROUP_ISSUES_BY_WEEK) {
                    $heading = userdate($from, $dateformat) . ' (' . userdate($from, $datefromto) . ' - ' . userdate(strtotime('yesterday', $to), $datefromto) . ')';
                } else {
                    $heading = userdate($from, $dateformat);
                }
                while ($issue->publishon < $from || $issue->publishon > $to) {
                    $from = $to;
                    switch ($groupby) {
                    case NEWSLETTER_GROUP_ISSUES_BY_YEAR:
                        $to = strtotime("next year", $from);
                        break;
                    case NEWSLETTER_GROUP_ISSUES_BY_MONTH:
                        $to = strtotime("next month", $from);
                        break;
                    case NEWSLETTER_GROUP_ISSUES_BY_WEEK:
                        $to = strtotime("Monday next week", $from);
                        break;
                    }
                }
                $sectionlist->add_issue_section(new newsletter_section($heading, $currentissuelist));
                $currentissuelist = new newsletter_issue_summary_list();
                $currentissuelist->add_issue_summary(new newsletter_issue_summary($issue, $editissue, $deleteissue));
            }
        }
        if (!empty($currentissuelist->issues)) {
            if ($groupby == NEWSLETTER_GROUP_ISSUES_BY_WEEK) {
                $heading = userdate($from, $dateformat) . ' (' . userdate($from, $datefromto) . ' - ' . userdate(strtotime('yesterday', $to), $datefromto) . ')';
            } else {
                $heading = userdate($from, $dateformat);
            }
            $sectionlist->add_issue_section(new newsletter_section($heading, $currentissuelist));
        }

        return $sectionlist;
    }

    private function calculate_pages($total, $from, $count) {
        $pages = array();
        $pagenum = 1;

        if ($total == 0) {
            $pages[0] = $pagenum;
            return $pages;
        }

        if ($from % $count !== 0) {
            $pages[0] = $pagenum;
            $pagenum++;
        }

        for ($i = $from % $count; $i < $total; $i += $count) {
            $pages[$i] = $pagenum;
            $pagenum++;
        }

        return $pages;
    }

    private function check_issue_id($issueid) {
        global $DB;

        return !$issueid || $DB->get_field('newsletter_issues', 'newsletterid',
                array('id' => $issueid, 'newsletterid' => $this->get_instance()->id));
    }

    private function add_issue(stdClass $data) {
        global $DB;
        $issue = new stdClass();
        $issue->id = 0;
        $issue->newsletterid = $this->get_instance()->id;
        $issue->title = $data->title;
        $issue->htmlcontent = $data->htmlcontent['text'];
        $issue->publishon = $data->publishon;
        $issue->stylesheetid = $data->stylesheetid;

        $issue->id = $DB->insert_record('newsletter_issues', $issue);

        $fileoptions = array('subdirs' => NEWSLETTER_FILE_OPTIONS_SUBDIRS,
                         'maxbytes' => 0,
                         'maxfiles' => -1);

        $context = $this->get_context();

        if ($data && $data->attachments) {
            file_save_draft_area_files($data->attachments, $context->id, 'mod_newsletter', NEWSLETTER_FILE_AREA_ATTACHMENTS, $issue->id, $fileoptions);
        }

        return $issue->id;
    }

    private function update_issue(stdClass $data) {
        global $DB;

        $issue = new stdClass();
        $issue->id = $data->issue;
        $issue->title = $data->title;
        $issue->newsletterid = $this->get_instance()->id;
        $issue->htmlcontent = $data->htmlcontent['text'];
        $issue->publishon = $data->publishon;
        $issue->stylesheetid = $data->stylesheetid;

        $context = $this->get_context();

        $fileoptions = array('subdirs' => NEWSLETTER_FILE_OPTIONS_SUBDIRS,
                         'maxbytes' => 0,
                         'maxfiles' => -1);

        if ($data && $data->attachments) {
            file_save_draft_area_files($data->attachments, $context->id, 'mod_newsletter', NEWSLETTER_FILE_AREA_ATTACHMENTS, $issue->id, $fileoptions);
        }

        $DB->update_record('newsletter_issues', $issue);
    }

    private function delete_issue($issueid) {
        global $DB;
        $DB->delete_records('newsletter_issues', array('id' => $issueid));
    }

    function subscribe_cohort($cohortid) {
        global $DB;
        $sql = "SELECT cm.userid
                  FROM {cohort_members} cm
                 WHERE cm.cohortid = :cohortid";
        $params = array('cohortid' => $cohortid);
        $userids = $DB->get_fieldset_sql($sql, $params);

        foreach ($userids as $userid) {
            $this->subscribe($userid, $this->get_instance()->id, true);
        }
    }

    function unsubscribe_cohort($cohortid) {
        global $DB;
        $sql = "SELECT cm.userid
                  FROM {cohort_members} cm
                 WHERE cm.cohortid = :cohortid";
        $params = array('cohortid' => $cohortid);
        $userids = $DB->get_fieldset_sql($sql, $params);

        foreach ($userids as $userid) {
            $this->unsubscribe($userid);
        }
    }

    private function get_issues($from = 0, $to = 0) {
        global $DB;
        $total = $DB->count_records('newsletter_subscriptions', array('newsletterid' => $this->get_instance()->id));

        $query = "SELECT i.*
                    FROM {newsletter_issues} i
                   WHERE i.newsletterid = :newsletterid
                     AND " . ($from ? " i.publishon > :from" : "1") .
                   " AND " . ($to ? " i.publishon > :to" : "1") .
              " ORDER BY i.publishon ASC";
        $params = array('newsletterid' => $this->get_instance()->id,
                                'from' => $from,
                                  'to' => $to);
        $records = $DB->get_records_sql($query, $params);
        foreach ($records as $record) {
            $record->cmid = $this->get_course_module()->id;
            if (isset($record->status)) {
                $data = json_decode($record->status, true);
                $record->numsubscriptions = count($data);
                $record->numdelivered = 0;
                foreach($data as $status) {
                    if($status === 1) {
                        $record->numdelivered++;
                    }
                }
            } else {
                $record->numsubscriptions = $total;
                $record->numdelivered = 0;
            }
        }
        return $records;
    }

    private function get_issue($issueid) {
        global $DB;
        if ($issueid == 0) {
            return null;
        }
        $record = $DB->get_record('newsletter_issues', array('id' => $issueid, 'newsletterid' => $this->get_instance()->id));
        if ($record) {
            $record->cmid = $this->get_course_module()->id;
            $record->context = $this->get_context()->id;
        }
        return $record;
    }

    public function get_stylesheets($id = 0) {
        $fs = get_file_storage();
        $context = $this->get_context();
        $files = $fs->get_area_files($context->id, 'mod_newsletter', NEWSLETTER_FILE_AREA_STYLESHEETS, $this->get_instance()->id, 'filename', false);
        if(!$id) {
            return $files;
        } else {
            foreach($files as $file) {
                if($file->get_id() == $id) {
                    return $file;
                }
            }
        }
        return null;
    }

    public function inline_css($htmlcontent, $stylesheetid, $fulldocument = false) {
        global $CFG;
        $cssfile = $this->get_stylesheets($stylesheetid);
        $basecss = file_get_contents(dirname(__FILE__) . '/' . NEWSLETTER_BASE_STYLESHEET_PATH);
        $css = $basecss . ($cssfile ? ('\n' . $cssfile->get_content()) : '');

        $converter = new CssToInlineStyles();
        $converter->setHTML($htmlcontent);
        $converter->setCSS($css);
        $html = $converter->convert();

        if (!$fulldocument) {
            if (preg_match('/.*?<html.*?style="([^"]*?)"[^>]*?>.*?<body.*?style="([^"]*?)"[^>]*?>(.+)<\/body>.*/msi', $html)) {
                $html = preg_replace('/.*?<html.*?style="([^"]*?)"[^>]*?>.*?<body.*?style="([^"]*?)"[^>]*?>(.+)<\/body>.*/msi', '<div style="$1 $2">$3</div>', $html);
            } else if (preg_match('/.*?<html[^>]*?>.*?<body.*?style="([^"]*?)"[^>]*?>(.+)<\/body>.*/msi', $html)) {
                $html = preg_replace('/.*?<html[^>]*?>.*?<body.*?style="([^"]*?)"[^>]*?>(.+)<\/body>.*/msi', '<div style="$1">$2</div>', $html);
            } else if (preg_match('/.*?<html.*?style="([^"]*?)"[^>]*?>.*?<body[^>]*?>(.+)<\/body>.*/msi', $html)) {
                $html = preg_replace('/.*?<html.*?style="([^"]*?)"[^>]*?>.*?<body[^>]*?>(.+)<\/body>.*/msi', '<div style="$1">$2</div>', $html);
            } else if (preg_match('/.*?<html[^>]*?>.*?<body[^>]*?>(.+)<\/body>.*/msi', $html)) {
                $html = preg_replace('/.*?<html[^>]*?>.*?<body[^>]*?>(.+)<\/body>.*/msi', '<div>$1</div>', $html);
            } else {
                $html = '';
            }
        }

        return $html;
    }

    private function get_previous_issue($issue) {
        global $DB;
        $query = "SELECT *
                    FROM {newsletter_issues} i
                   WHERE i.newsletterid = :newsletterid
                     AND i.publishon < :publishon
                ORDER BY i.publishon DESC";
        $params = array('newsletterid' => $issue->newsletterid, 'publishon' => $issue->publishon);
        $results = $DB->get_records_sql($query, $params);
        return empty($results) ? null : reset($results);
    }

    private function get_next_issue($issue) {
        global $DB;
        $query = "SELECT *
                    FROM {newsletter_issues} i
                   WHERE i.newsletterid = :newsletterid
                     AND i.publishon > :publishon
                ORDER BY i.publishon ASC";
        $params = array('newsletterid' => $issue->newsletterid, 'publishon' => $issue->publishon);
        $results = $DB->get_records_sql($query, $params);
        return empty($results) ? null : reset($results);
    }

    private function get_first_issue($issue) {
        global $DB;
        $query = "SELECT *
                    FROM {newsletter_issues} i
                   WHERE i.newsletterid = :newsletterid
                     AND i.publishon < :publishon
                     AND i.id != :id
                ORDER BY i.publishon ASC";
        $params = array('newsletterid' => $issue->newsletterid, 'publishon' => $issue->publishon, 'id' => $issue->id);
        $results = $DB->get_records_sql($query, $params);
        return empty($results) ? null : reset($results);
    }

    private function get_last_issue($issue) {
        global $DB;
        $query = "SELECT *
                    FROM {newsletter_issues} i
                   WHERE i.newsletterid = :newsletterid
                     AND i.publishon > :publishon
                     AND i.id != :id
                ORDER BY i.publishon DESC";
        $params = array('newsletterid' => $issue->newsletterid, 'publishon' => $issue->publishon, 'id' => $issue->id);
        $results = $DB->get_records_sql($query, $params);
        return empty($results) ? null : reset($results);
    }

    public function subscribe($userid = 0, $bulk = false, $status = NEWSLETTER_SUBSCRIBER_STATUS_OK) {
        global $DB, $USER;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        if ($sub = $DB->get_record("newsletter_subscriptions", array("userid" => $userid, "newsletterid" => $this->get_instance()->id))) {
            if($sub->health == NEWSLETTER_SUBSCRIBER_STATUS_UNSUBSCRIBED) {
                return $DB->set_field('newsletter_subscriptions', 'health', NEWSLETTER_SUBSCRIBER_STATUS_OK, array('userid' => $userid, "newsletterid" => $this->get_instance()->id));
            }
        }

        $sub = new stdClass();
        $sub->userid  = $userid;
        $sub->newsletterid = $this->get_instance()->id;
        $sub->health = $status;

        return $DB->insert_record("newsletter_subscriptions", $sub, true, $bulk);
    }

    private function update_subscription(stdClass $data) {
        global $DB;

        $subscription = new stdClass();
        $subscription->id = $data->subscription;
        $subscription->health = $data->health;

        $DB->update_record('newsletter_subscriptions', $subscription);
    }

    public function delete_subscription($subid) {
        global $DB;
        return $DB->delete_records("newsletter_subscriptions", array('id' => $subid));
    }

    public function unsubscribe($userid = 0) {
        global $DB, $USER;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        return $DB->set_field('newsletter_subscriptions', 'health', NEWSLETTER_SUBSCRIBER_STATUS_UNSUBSCRIBED, array('userid' => $userid));
    }

    public function is_subscribed($userid = 0) {
        global $DB, $USER;
        if (!$userid) {
            $userid = $USER->id;
        }

        return $DB->record_exists_select("newsletter_subscriptions", "userid = :userid AND newsletterid = :newsletterid AND health <> :health",
                                        array("userid" => $userid, "newsletterid" => $this->get_instance()->id, "health" => NEWSLETTER_SUBSCRIBER_STATUS_UNSUBSCRIBED));
    }

    public function subscribe_guest($firstname, $lastname, $email) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        $cfirstname = preg_replace('/[^a-zA-Z]+/', '', iconv('UTF-8', 'US-ASCII//TRANSLIT', $firstname));
        $clastname = preg_replace('/[^a-zA-Z]+/', '', iconv('UTF-8', 'US-ASCII//TRANSLIT', $lastname));
        $username = strtolower(substr($cfirstname, 0, 1) . $clastname);

        $i = 0;
        do {
            $newusername = $username . ($i != 0 ? $i : '');
            $i++;
            $olduser = get_complete_user_data('username', $newusername);
        } while (!empty($olduser));

        $user = new stdClass();
        $user->username    = $newusername;
        $user->email       = $email;
        $user->firstname   = $firstname;
        $user->lastname    = $lastname;
        $user->password    = $password = generate_password();
        $user->mailformat  = 1;
        $user->confirmed   = 0;
        $user->lang        = current_language();
        $user->firstaccess = time();
        $user->timecreated = time();
        $user->mnethostid  = $CFG->mnet_localhost_id;
        $user->secret      = $secret = random_string(15);
        $user->auth        = $CFG->registerauth;

        $user->password = hash_internal_user_password($user->password);

        $user->id = $DB->insert_record('user', $user);

        /// Save any custom profile field information
        profile_save_data($user);

        $user = $DB->get_record('user', array('id'=>$user->id));
        events_trigger('user_created', $user);

        $this->subscribe($user->id, false, NEWSLETTER_SUBSCRIBER_STATUS_OK);

        $cm = $this->get_course_module();
        $newslettername = $DB->get_field('newsletter', 'name', array('id' => $cm->instance));

        $data = "{$secret}-{$user->id}";
        $activateurl = new moodle_url('/mod/newsletter/confirm.php', array(NEWSLETTER_PARAM_DATA => $data));

        $site = get_site();
        $a = array(
            'fullname' => fullname($user),
            'newslettername' => $newslettername,
            'sitename' => format_string($site->fullname),
            'email' => $email,
            'username' => $user->username,
            'password' => $password,
            'link' => $activateurl->__toString(),
            'admin' => generate_email_signoff());

        $htmlcontent = text_to_html(get_string('new_user_subscribe_message', 'newsletter', $a));

        if (!email_to_user($user, "newsletter", "Welcome", '', $htmlcontent)) {
            return false;
        }

        return true;
    }
}