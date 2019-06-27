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
 * This file contains a renderer for the newsletter module
 *
 * @package mod_newsletter
 * @copyright 2013 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * A custom renderer class that extends the plugin_renderer_base and is used by the newsletter
 * module.
 */
class mod_newsletter_renderer extends plugin_renderer_base {

    public function render_newsletter_header(newsletter_header $header) {
        $output = '';

        $this->page->set_title(get_string('pluginname', 'mod_newsletter'));
        $this->page->set_heading($header->newsletter->name);

        $output .= $this->output->header();

        if ($header->showintro) {
            $output .= $this->output->box_start('generalbox boxaligncenter', 'intro');
            $output .= format_module_intro('newsletter', $header->assign, $header->coursemoduleid);
            $output .= $this->output->box_end();
        }

        return $output;
    }

    /**
     * Renders a generic form
     *
     * @param assign_form $form The form to render
     * @return string
     */
    public function render_newsletter_form(newsletter_form $form) {
        $output = '';
        if ($form->jsinitfunction) {
            $this->page->requires->js_init_call($form->jsinitfunction, array());
        }
        $output .= $this->output->box_start('boxaligncenter ' . $form->classname);
        $output .= $this->moodleform($form->form);
        $output .= $this->output->box_end();

        return $output;
    }

    public function render_newsletter_section_list(newsletter_section_list $sectionlist) {
        $output = '';

        $output .= html_writer::start_tag('div', array('class' => 'mod_newsletter__section-list'));
        /*
         * // Temporarily unused
         * $output .= html_writer::start_tag('h3');
         * $output .= $sectionlist->heading;
         * $output .= html_writer::end_tag('h3');
         * //
         */
        $output .= html_writer::start_tag('ul');
        foreach ($sectionlist->sections as $section) {
            $output .= html_writer::start_tag('li');
            $output .= $this->render($section);
            $output .= html_writer::end_tag('li');
        }
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    public function render_newsletter_section(newsletter_section $section) {
        $output = '';

        $output .= html_writer::start_tag('div', array('class' => 'mod_newsletter__section'));
        $output .= html_writer::start_tag('h3');
        $output .= $section->heading;
        $output .= html_writer::end_tag('h3');
        $output .= $this->render($section->summarylist);
        $output .= html_writer::end_tag('div');

        return $output;
    }

    public function render_newsletter_issue_summary_list(newsletter_issue_summary_list $list) {
        $output = '';

        $output .= html_writer::start_tag('div', array('class' => 'mod_newsletter__issue-list'));
        $output .= html_writer::start_tag('ul');
        foreach ($list->issues as $issue) {
            $output .= html_writer::start_tag('li');
            $output .= $this->render($issue);
            $output .= html_writer::end_tag('li');
        }
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    public function render_newsletter_issue(newsletter_issue $issue) {
        $output = '';
        $output .= html_writer::start_tag('div',
                array('class' => 'mod_newsletter__issue--full__container'));
        $output .= $issue->htmlcontent;
        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function render_newsletter_issue_summary(newsletter_issue_summary $issue) {
        global $OUTPUT, $CFG;

        $link = '';
        if ($CFG->branch >= 33) {
            $link .= $OUTPUT->image_icon('icon', '', 'mod_newsletter',
                    array('class' => 'mod_newsletter__issue--summary__link-read-icon'));
        } else {
            $link .= html_writer::empty_tag('img',
                    array('src' => $this->output->pix_url('icon', 'mod_newsletter'),
                        'class' => 'mod_newsletter__issue--summary__link-read-icon'));
        }
        $link .= html_writer::start_tag('span');
        $link .= $issue->title . " (" . userdate($issue->publishon, '%d %B %Y') . ")";
        $link .= html_writer::end_tag('span');
        $url = new moodle_url('/mod/newsletter/view.php',
                array('id' => $issue->cmid, 'action' => NEWSLETTER_ACTION_READ_ISSUE,
                    'issue' => $issue->id));

        $now = time();
        $output = '';
        $output .= html_writer::start_tag('div', array('class' => 'mod_newsletter__issue--summary'));
        $output .= html_writer::start_tag('div',
                array('class' => 'mod_newsletter__issue--summary__link-read'));
        $output .= html_writer::link($url, $link);
        $output .= html_writer::end_tag('div');
        if ($issue->editissue) {
            $output .= html_writer::start_tag('div',
                    array('class' => 'mod_newsletter__issue--summary__link-edit'));
            $output .= $this->render(
                    new newsletter_action_button($issue->cmid, $issue->id,
                            NEWSLETTER_ACTION_EDIT_ISSUE, get_string('edit_issue', 'mod_newsletter')));
            $output .= html_writer::end_tag('div');
        }
        if ($now < $issue->publishon && $issue->deleteissue) {
            $output .= html_writer::start_tag('div',
                    array('class' => 'mod_newsletter__issue--summary__link-edit'));
            $output .= $this->render(
                    new newsletter_action_button($issue->cmid, $issue->id,
                            NEWSLETTER_ACTION_DELETE_ISSUE,
                            get_string('delete_issue', 'mod_newsletter')));
            $output .= html_writer::end_tag('div');
        }
        if ($now > $issue->publishon) {
            if ($issue->editissue) {
                $output .= $this->render(
                        new newsletter_progressbar($issue->numnotyetdelivered, $issue->numdelivered));
            }
        } else {
            $output .= $this->render(new newsletter_publish_countdown($now, $issue->publishon));
        }
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     *
     * @param newsletter_navigation_bar $navigationbar
     * @return string
     */
    public function render_newsletter_navigation_bar(newsletter_navigation_bar $navigationbar) {
        $url = new moodle_url('/mod/newsletter/view.php');
        if (!empty($navigationbar->firstissue)) {
            $urlparams = array('id' => $navigationbar->currentissue->cmid,
                'action' => NEWSLETTER_ACTION_READ_ISSUE, 'issue' => $navigationbar->firstissue->id);
            $link = html_writer::link(new moodle_url($url, $urlparams), '',
                    array('class' => 'mod-newsletter__navigation-bar__button--first'));
        }

        $firstissuelink = $navigationbar->firstissue ? $link : '';
        if (!empty($navigationbar->previousissue)) {
            $urlparams = array('id' => $navigationbar->currentissue->cmid,
                'action' => NEWSLETTER_ACTION_READ_ISSUE,
                'issue' => $navigationbar->previousissue->id);
            $link = html_writer::link(new moodle_url($url, $urlparams), '',
                    array('class' => 'mod-newsletter__navigation-bar__button--previous'));
        }

        $previousissuelink = $navigationbar->previousissue ? $link : '';
        if (!empty($navigationbar->nextissue)) {
            $urlparams = array('id' => $navigationbar->currentissue->cmid,
                'action' => NEWSLETTER_ACTION_READ_ISSUE, 'issue' => $navigationbar->nextissue->id);
            $link = html_writer::link(new moodle_url($url, $urlparams), '',
                    array('class' => 'mod-newsletter__navigation-bar__button--next'));
        }

        $nextissuelink = $navigationbar->nextissue ? $link : '';
        if (!empty($navigationbar->lastissue)) {
            $urlparams = array('id' => $navigationbar->currentissue->cmid,
                'action' => NEWSLETTER_ACTION_READ_ISSUE, 'issue' => $navigationbar->lastissue->id);
            $link = html_writer::link(new moodle_url($url, $urlparams), '',
                    array('class' => 'mod-newsletter__navigation-bar__button--last'));
        }
        $lastissuelink = $navigationbar->lastissue ? $link : '';

        $output = '';
        $output .= html_writer::start_tag('div',
                array('class' => 'mod-newsletter__navigation-bar__container'));
        $output .= html_writer::start_tag('div', array('class' => 'mod-newsletter__navigation-bar'));
        $output .= $firstissuelink;
        $output .= $previousissuelink;
        $output .= html_writer::start_tag('span',
                array('class' => 'mod-newsletter__navigation-bar__title'));
        $output .= "{$navigationbar->currentissue->title}";
        $output .= html_writer::end_tag('span');
        $output .= $nextissuelink;
        $output .= $lastissuelink;
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     *
     * @param newsletter_action_button $button
     * @return string
     */
    public function render_newsletter_action_button(newsletter_action_button $button) {
        if ($button->issueid !== 0) {
            $url = new moodle_url('/mod/newsletter/view.php',
                    array('id' => $button->cmid, 'action' => $button->action,
                        'issue' => $button->issueid));
        } else {
            $url = new moodle_url('/mod/newsletter/view.php',
                    array('id' => $button->cmid, 'action' => $button->action));
        }
        $output = '';
        $output .= html_writer::start_tag('div', array('class' => 'mod-newsletter__action-link'));
        $output .= html_writer::link($url, $button->label);
        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function render_newsletter_main_toolbar(newsletter_main_toolbar $toolbar) {
        global $CFG;

        $output = '';
        $output .= html_writer::start_tag('div');
        $output .= html_writer::start_tag('form',
                array('method' => 'GET', 'action' => new moodle_url('/mod/newsletter/view.php')));
        $output .= html_writer::empty_tag('input',
                array('type' => 'hidden', 'name' => 'id', 'value' => $toolbar->cmid));

        $options = array(NEWSLETTER_GROUP_ISSUES_BY_YEAR => get_string('year'),
            NEWSLETTER_GROUP_ISSUES_BY_MONTH => get_string('month'),
            NEWSLETTER_GROUP_ISSUES_BY_WEEK => get_string('week'));
        $output .= html_writer::tag('span', get_string('groupby', 'mod_newsletter'));
        $output .= html_writer::select($options, NEWSLETTER_PARAM_GROUP_BY, $toolbar->groupby, false);

        // Add pagination.
        $options = array(1 => 1, 2 => 2, 10 => 10, 50 => 50, 100 => 100);
        $output .= html_writer::tag('span', get_string('entries_per_page', 'mod_newsletter') .": ");
        $output .= html_writer::select($options, NEWSLETTER_PARAM_COUNT, $toolbar->count, false);

        // If we refresh the settings we always start with the first issue.
        $output .= html_writer::empty_tag('input',
                array('type' => 'hidden', 'name' => 'fromissue', 'value' => NEWSLETTER_FROMISSUE_DEFAULT));

        if ($CFG->branch >= 33) {
            $output .= html_writer::empty_tag('input',
                    array('type' => 'submit', 'value' => get_string('refresh'),
                        'class' => 'btn btn-secondary', 'style' => 'margin-left:5px;'));
        } else {
            $output .= html_writer::empty_tag('input',
                    array('type' => 'submit', 'value' => get_string('refresh')));
        }

        $output .= html_writer::end_tag('form');

        // Pagination Next and Previous.
        $prevfromissue = $toolbar->fromissue - $toolbar->count;
        $prevurl = new moodle_url('/mod/newsletter/view.php',
                array('id' => $toolbar->cmid, 'groupby' => $toolbar->groupby,
                    'count' => $toolbar->count, 'fromissue' => $prevfromissue));
        $nextfromissue = $toolbar->fromissue + $toolbar->count;
        $nexturl = new moodle_url('/mod/newsletter/view.php',
                array('id' => $toolbar->cmid, 'groupby' => $toolbar->groupby,
                    'count' => $toolbar->count, 'fromissue' => $nextfromissue));
        $output .= html_writer::link($prevurl, "Previous Page") . " | "; // TODO: Multilang.
        $output .= html_writer::link($nexturl, "Next Page"); // TODO: Multilang.

        if ($toolbar->createissues) {
            $output .= $this->render(
                    new newsletter_action_button($toolbar->cmid, 0, NEWSLETTER_ACTION_CREATE_ISSUE,
                            get_string('create_new_issue', 'mod_newsletter')));
        }
        if ($toolbar->managesubs) {
            $output .= $this->render(
                    new newsletter_action_button($toolbar->cmid, 0,
                            NEWSLETTER_ACTION_MANAGE_SUBSCRIPTIONS,
                            get_string('manage_subscriptions', 'mod_newsletter')));
        }
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Renders the footer
     *
     * @return void
     */
    public function render_footer() {
        return $this->output->footer();
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param moodleform $mform
     * @return string HTML of the form
     */
    public function moodleform(moodleform $mform) {
        $output = '';
        ob_start();
        $mform->display();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    public function render_newsletter_progressbar(newsletter_progressbar $progressbar) {
        $output = '';

        if (($progressbar->completed + $progressbar->tocomplete) == 0) {
            return $output;
        }
        $value = $progressbar->completed / ($progressbar->completed + $progressbar->tocomplete) * 100;
        $invertedvalue = 100 - $value;
        if ($progressbar->tocomplete > 0) {
            $remaining = $progressbar->tocomplete;
        } else {
            $remaining = '';
        }
        if ($progressbar->completed > 0) {
            $completed = $progressbar->completed;
        } else {
            $completed = '';
        }

        $output .= html_writer::start_tag('div', array('class' => 'mod_newsletter_progress'));
        $output .= html_writer::div($completed, '',
                array('class' => 'mod_newsletter_progress-bar', 'role' => 'progressbar',
                    'aria-valuenow' => $value, 'aria-valuemin' => '0', 'aria-valuemax' => '100',
                    'style' => 'width:' . $value . '%'));
        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function render_newsletter_subscription_list(newsletter_subscription_list $list) {
        global $OUTPUT, $CFG;

        $table = new html_table();

        $header = array();
        foreach ($list->columns as $column) {
            switch ($column) {
                case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_EMAIL:
                    $content = get_string('header_email', 'mod_newsletter');
                    break;
                case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_NAME:
                    $content = get_string('header_name', 'mod_newsletter');
                    break;
                case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_HEALTH:
                    $content = get_string('header_health', 'mod_newsletter');
                    break;
                case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_TIMESUBSCRIBED:
                    $content = get_string('header_timesubscribed', 'mod_newsletter');
                    break;
                case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_ACTIONS:
                    $content = get_string('header_actions', 'mod_newsletter');
                    break;
                default:
                    print_error('Unsupported column type: ' . $column);
                    break;
            }
            $cell = new html_table_cell($content);
            $cell->header = true;
            $header[] = $cell;
        }
        $table->head = $header;

        $rows = array();
        foreach ($list->subscriptions as $subscription) {
            $row = $rows[] = new html_table_row();
            foreach ($list->columns as $column) {
                switch ($column) {
                    case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_EMAIL:
                        $content = html_writer::link("mailto:{$subscription->email}",
                                $subscription->email, array('target' => '_blank'));
                        break;
                    case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_NAME:
                        $profileurl = new moodle_url('/user/view.php',
                                array('id' => $subscription->userid));
                        $name = fullname($subscription);
                        $content = html_writer::link($profileurl, $name);
                        break;
                    case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_HEALTH:
                        $content = get_string("health_{$subscription->health}", 'newsletter');
                        $content .= " (" . $this->newsletter_count_bounces(
                                $subscription->newsletterid, $subscription->userid) . ")";
                        break;
                    case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_TIMESUBSCRIBED:
                        $content = userdate($subscription->timesubscribed,
                                get_string('strftimedate'));
                        break;
                    case NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_ACTIONS:
                        $url = new moodle_url('/mod/newsletter/view.php',
                                array(NEWSLETTER_PARAM_ID => $list->cmid,
                                    NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_EDIT_SUBSCRIPTION,
                                    NEWSLETTER_PARAM_SUBSCRIPTION => $subscription->id));
                        if ($CFG->branch >= 33) {
                            $content = \html_writer::link($url,
                                    $OUTPUT->image_icon('t/edit', get_string('edit')),
                                    array('class' => 'editbutton', 'title' => get_string('edit')));
                        } else {
                            $content = \html_writer::link($url,
                                    \html_writer::empty_tag('img',
                                            array('src' => $OUTPUT->pix_url('t/edit'))),
                                    array('class' => 'editbutton', 'title' => get_string('edit')));
                        }
                        $url = new \moodle_url('/mod/newsletter/view.php',
                                array(NEWSLETTER_PARAM_ID => $list->cmid,
                                    NEWSLETTER_PARAM_ACTION => NEWSLETTER_ACTION_DELETE_SUBSCRIPTION,
                                    NEWSLETTER_PARAM_SUBSCRIPTION => $subscription->id));
                        if ($CFG->branch >= 33) {
                            $content .= \html_writer::link($url,
                                    $OUTPUT->image_icon('t/delete', get_string('delete')),
                                    array('class' => 'deletebutton',
                                        'title' => get_string('delete')));
                        } else {
                            $content .= \html_writer::link($url,
                                    \html_writer::empty_tag('img',
                                            array('src' => $OUTPUT->pix_url('t/delete'))),
                                    array('class' => 'deletebutton',
                                        'title' => get_string('delete')));
                        }
                        break;
                    default:
                        print_error('Unsupported column type: ' . $column);
                        break;
                }
                $cell = new html_table_cell($content);
                $row->cells[] = $cell;
            }
        }
        $table->data = $rows;
        return html_writer::table($table);
    }

    public function render_newsletter_publish_countdown(newsletter_publish_countdown $countdown) {
        $output = '';
        $output .= html_writer::start_tag('span');
        if ($countdown->now > $countdown->until) {
            $output .= get_string('already_published', 'mod_newsletter');
        } else {
            $a = array();
            list($a['days'], $a['hours'], $a['minutes'], $a['seconds']) = $this->newsletter_get_countdown(
                    $countdown->until - $countdown->now);
            $output .= get_string('publish_in', 'newsletter', $a);
        }
        $output .= html_writer::end_tag('span');
        return $output;
    }

    private function newsletter_get_countdown($time) {
        $secsinday = 24 * ($secsinhour = 60 * ($secsinmin = 60));
        $days = intval($time / $secsinday);
        $hrs = intval(($time % $secsinday) / $secsinhour);
        $min = intval(($time % $secsinhour) / $secsinmin);
        $sec = intval($time % $secsinmin);
        return array($days, $hrs, $min, $sec);
    }

    private function newsletter_count_bounces($newsletterid, $userid) {
        global $DB;

        $bounces = 0;
        $sql = "SELECT count(*)
		        FROM {newsletter_issues} ni
				INNER JOIN {newsletter_bounces} nb on ni.id = nb.issueid
		        WHERE ni.newsletterid = :newsletterid
		        AND nb.userid = :userid";
        $params = array('newsletterid' => $newsletterid, 'userid' => $userid);
        $bounces = $DB->count_records_sql($sql, $params);
        return $bounces;
    }

    public function render_newsletter_pager(newsletter_pager $pager) {
        $url = $pager->url;
        $pagefrom = array_keys($pager->pages);
        $from = $pager->from;
        $firstpage = reset($pagefrom);
        $lastpage = end($pagefrom);
        $previouspage = ($from - $pager->count >= $pager->count) ? $from - $pager->count : $firstpage;
        $nextpage = ($from + $pager->count <= $lastpage) ? $from + $pager->count : $lastpage;

        $output = '';
        $output .= html_writer::start_tag('div', array('class' => 'mod_newsletter__pager'));
        $output .= html_writer::span(get_string('allusers', 'mod_newsletter') . $pager->totalentries);
        $output .= html_writer::span(
                get_string('filteredusers', 'mod_newsletter') . $pager->totalfiltered);
        $output .= html_writer::start_tag('ul', array());
        if ($from != $firstpage) {
            $output .= html_writer::start_tag('li');
            $output .= html_writer::link(
                    new moodle_url($url, array('from' => $firstpage, 'count' => $pager->count)),
                    get_string('page_first', 'mod_newsletter'),
                    array('class' => 'mod_newsletter__pager__link'));
            $output .= html_writer::end_tag('li');
            $output .= html_writer::start_tag('li');
            $output .= html_writer::link(
                    new moodle_url($url, array('from' => $previouspage, 'count' => $pager->count)),
                    get_string('page_previous', 'mod_newsletter'),
                    array('class' => 'mod_newsletter__pager__link'));
            $output .= html_writer::end_tag('li');
        } else {
            $output .= html_writer::start_tag('li');
            $output .= get_string('page_first', 'mod_newsletter');
            $output .= html_writer::end_tag('li');
            $output .= html_writer::start_tag('li');
            $output .= get_string('page_previous', 'mod_newsletter');
            $output .= html_writer::end_tag('li');
        }

        for ($i = max($firstpage,
                $from - $pager->count * 2); $i <= min($lastpage, $from + $pager->count * 2); $i += $pager->count) {
            if ($i == $pager->from) {
                $output .= html_writer::start_tag('li');
                $output .= $pager->pages[$i];
                $output .= html_writer::end_tag('li');
            } else {
                $output .= html_writer::start_tag('li');
                $output .= html_writer::link(
                        new moodle_url($url, array('from' => $i, 'count' => $pager->count)),
                        $pager->pages[$i], array('class' => 'mod_newsletter__pager__link'));
                $output .= html_writer::end_tag('li');
            }
        }

        if ($from != $lastpage) {
            $output .= html_writer::start_tag('li');
            $output .= html_writer::link(
                    new moodle_url($url, array('from' => $nextpage, 'count' => $pager->count)),
                    get_string('page_next', 'mod_newsletter'),
                    array('class' => 'mod_newsletter__pager__link'));
            $output .= html_writer::end_tag('li');
            $output .= html_writer::start_tag('li');
            $output .= html_writer::link(
                    new moodle_url($url, array('from' => $lastpage, 'count' => $pager->count)),
                    get_string('page_last', 'mod_newsletter'),
                    array('class' => 'mod_newsletter__pager__link'));
            $output .= html_writer::end_tag('li');
        } else {
            $output .= html_writer::start_tag('li');
            $output .= get_string('page_next', 'mod_newsletter');
            $output .= html_writer::end_tag('li');
            $output .= html_writer::start_tag('li');
            $output .= get_string('page_last', 'mod_newsletter');
            $output .= html_writer::end_tag('li');
        }
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function render_newsletter_attachment_list(newsletter_attachment_list $list) {
        global $OUTPUT;
        $output = '';
        $output .= html_writer::start_tag('div', array(
            'class' => 'mod_newsletter__attachment_list'));
        $output .= html_writer::start_tag('h3');
        $output .= get_string('attachments', 'mod_newsletter');
        $output .= html_writer::end_tag('h3');
        $output .= html_writer::start_tag('ul');
        foreach ($list->files as $file) {
            $output .= html_writer::start_tag('li');
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file),
                    'moodle', array('class' => 'icon'));
            $output .= html_writer::link($file->link, $iconimage . " " . $file->get_filename());
            $output .= html_writer::end_tag('li');
        }
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function render_newsletter_attachment_list_empty() {
        $output = '';
        $output .= html_writer::start_tag('div', array(
            'class' => 'mod_newsletter__attachment_list'));
        $output .= html_writer::start_tag('h3');
        $output .= get_string('attachments_no', 'mod_newsletter');
        $output .= html_writer::end_tag('h3');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function render_newsletter_action_link(newsletter_action_link $link) {
        $output = '';
        $output .= html_writer::start_tag('span');
        $output .= html_writer::link($link->url, $link->text, array('class' => $link->class));
        $output .= html_writer::end_tag('span');
        return $output;
    }
}
