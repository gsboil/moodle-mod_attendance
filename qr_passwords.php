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
 * Displays help via AJAX call or in a new page
 *
 * Use {@see core_renderer::help_icon()} or {@see addHelpButton()} to display
 * the help icon.
 *
 * @copyright  2021 University of Otago
 * @package    mod_attendance
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/pdflib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/tcpdf/tcpdf_barcodes_2d.php'); // Used for generating qrcode.


function attendance_exportqrtopdf($course_shortname, $course_fullname, $sessiondata, $filename) {
    global $DB;
    global $CFG;
    $filename .= ".pdf";
    // set style for barcode
    $style = array(
	    'border' => 2,
	    'vpadding' => 'auto',
	    'hpadding' => 'auto',
	    'fgcolor' => array(0,0,0),
	    'bgcolor' => false, //array(255,255,255)
	    'module_width' => 1, // width of a single module in points
	    'module_height' => 1 // height of a single module in points
    );

    $pdf = new pdf();    
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Moodle Attendance');
    $pdf->SetTitle($course_shortname.'QR-Codes');
    $pdf->setPrintHeader(false);

    // Set margins.
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks.
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

    // Set image scale factor.
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    $qrcount = 0;
    $qrcodeurl_start = $CFG->wwwroot . '/mod/attendance/attendance.php?qrpass='; 
    $sessions = $DB->get_records_list('attendance_sessions', "id", $sessiondata, '', '*');
    foreach ($sessions as $session) {
        $showpassword = (isset($session->studentpassword) && strlen($session->studentpassword) > 0);
        $showqr = (isset($session->includeqrcode) && $session->includeqrcode == 1);
        if ($showpassword && $showqr){
            $pdf->AddPage('P');
            $html = html_writer::tag('h1', $course_fullname);
            $html .= html_writer::tag('h3', $session->description);                   
            $pdf->writeHTML($html); 
            $qrcodeurl = $qrcodeurl_start . $session->studentpassword . '&sessid=' . $session->id;
            $pdf->write2DBarcode($qrcodeurl, 'QRCODE,L', 30, 60, 150, 150, $style, 'N'); 
            // codetype, offset-x,y, width-x,y, style, align
            $pdf->writeHTML(html_writer::tag('h1', $session->studentpassword)); 
            $qrcount += 1; 
        }
    }
    if ($qrcount == 0){
        $pdf->AddPage('P');
        $pdf->writeHTML(html_writer::tag('h2', $course_fullname));
        $pdf->writehtml(html_writer::tag('h1', "No passwords, no QR codes"));
    }
    $pdf->Output($filename, 'D');   //sent as attachment -side-effect is we go back to old page, for free 
}
