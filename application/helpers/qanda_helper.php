<?php
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// Security Checked: POST, GET, SESSION, REQUEST, returnGlobal, DB

//if (!isset($homedir) || isset($_REQUEST['$homedir'])) {die("Cannot run this script directly");}

/*
* Let's explain what this strange $ia var means
*
* The $ia string comes from the $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['insertarray'] variable which is built at the commencement of the survey.
* See index.php, function "buildsurveysession()"
* One $ia array exists for every question in the survey. The $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['insertarray']
* string is an array of $ia arrays.
*
* $ia[0] => question id
* $ia[1] => fieldname
* $ia[2] => title
* $ia[3] => question text
* $ia[4] => type --  text, radio, select, array, etc
* $ia[5] => group id
* $ia[6] => mandatory Y || N
* $ia[7] => conditions exist for this question
* $ia[8] => other questions have conditions which rely on this question (including array_filter and array_filter_exclude attributes)
* $ia[9] => incremental question count (used by {QUESTION_NUMBER})
*
* $conditions element structure
* $condition[n][0] => qid = question id
* $condition[n][1] => cqid = question id of the target question, or 0 for TokenAttr leftOperand
* $condition[n][2] => field name of element [1] (Except for type M or P)
* $condition[n][3] => value to be evaluated on answers labeled.
* $condition[n][4] => type of question
* $condition[n][5] => SGQ code of element [1] (sub-part of [2])
* $condition[n][6] => method used to evaluate
* $condition[n][7] => scenario *NEW BY R.L.J. van den Burg*
*/

/**
* setNoAnswerMode
*/
function setNoAnswerMode($thissurvey)
{
    if (getGlobalSetting('shownoanswer') == 1)
    {
        define('SHOW_NO_ANSWER', 1);
    }
    elseif (getGlobalSetting('shownoanswer') == 0)
    {
        define('SHOW_NO_ANSWER', 0);
    }
    elseif ($thissurvey['shownoanswer'] == 'N')
    {
        define('SHOW_NO_ANSWER', 0);
    }
    else
    {
        define('SHOW_NO_ANSWER', 1);
    }
}

/**
* This function returns an array containing the "question/answer" html display
* and a list of the question/answer fieldnames associated. It is called from
* question.php, group.php, survey.php or preview.php
*
* @param mixed $ia
* @return mixed
*/
function retrieveAnswers($ia)
{
    //globalise required config variables
    global $thissurvey;                          //These are set by index.php

    $display    = $ia[7];                        //DISPLAY
    $name       = $ia[0];                        //QUESTION NAME
    $qtitle     = $ia[3];
    $inputnames = array();
    $answer     = "";                            //Create the question/answer html
    $number     = isset($ia[9]) ? $ia[9] : '';   // Previously in limesurvey, it was virtually impossible to control how the start of questions were formatted. // this is an attempt to allow users (or rather system admins) some control over how the starting text is formatted.
    $lang       = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang'];
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    $question_text = array(
        'all'                 => ''              // All has been added for backwards compatibility with templates that use question_start.pstpl (now redundant)
        ,'text'               => $qtitle
        ,'code'               => $ia[2]
        ,'number'             => $number
        ,'help'               => ''
        ,'mandatory'          => ''
        ,'man_message'        => ''
        ,'valid_message'      => ''
        ,'file_valid_message' => ''
        ,'class'              => ''
        ,'man_class'          => ''
        ,'input_error_class'  => ''              // provides a class.
        ,'essentials'         => ''
    );



    $oQuestion = Question::model()->findByPk(array('qid'=>$ia[0], 'language'=>$lang));

    switch ($ia[4])
    {
        case 'X': //BOILERPLATE QUESTION
            $values = do_boilerplate($ia);
            break;

        case '5': //5 POINT CHOICE radio-buttons
            $values = do_5pointchoice($ia);
            break;

        case 'D': //DATE
            $values = do_date($ia);
            // if a drop box style date was answered incompletely (dropbox), print an error/help message
            if (($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['step'] != $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['maxstep']) ||
                ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['step'] == $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['prevstep']))
                {
                    if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['qattribute_answer'.$ia[1]]))
                    {
                        $message = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['qattribute_answer'.$ia[1]];
                        $question_text['help'] = Yii::app()->getController()->renderPartial('/survey/question_help/error', array('message'=>$message, 'classes'=>''), true);
                    }
                }
            break;

        case 'L': //LIST drop-down/radio-button list
            $values = do_list_radio($ia);
            if ($aQuestionAttributes['hide_tip']==0)
            {
                $question_text['help'] = $message = gT('Choose one of the following answers');
                $qtitle .= Yii::app()->getController()->renderPartial('/survey/question_help/help', array('message'=>$message, 'classes'=>''), true);
            }
            break;

        case '!': //List - dropdown
            $values=do_list_dropdown($ia);
            if ($aQuestionAttributes['hide_tip']==0)
            {
                $question_text['help'] = $message = gT('Choose one of the following answers');
                $qtitle .= Yii::app()->getController()->renderPartial('/survey/question_help/help', array('message'=>$message, 'classes'=>''), true);
            }
            break;

        case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
            $values=do_listwithcomment($ia);
            if (count($values[1]) > 1 && $aQuestionAttributes['hide_tip']==0)
            {
                $question_text['help'] = $message = gT('Choose one of the following answers');
                $qtitle .= Yii::app()->getController()->renderPartial('/survey/question_help/help', array('message'=>$message, 'classes'=>''), true);
            }
            break;

        case 'R': //RANKING STYLE
            $values=do_ranking($ia);
            break;

        case 'M': //Multiple choice checkbox
            $values=do_multiplechoice($ia);
            if (count($values[1]) > 1 && $aQuestionAttributes['hide_tip']==0)
            {
                $maxansw=trim($aQuestionAttributes['max_answers']);
                $minansw=trim($aQuestionAttributes['min_answers']);
                if (!($maxansw || $minansw))
                {
                    $question_text['help'] = $message = gT('Check any that apply');
                    $qtitle .= Yii::app()->getController()->renderPartial('/survey/question_help/help', array('message'=>$message, 'classes'=>''), true);
                }
            }
            break;

        case 'I': //Language Question
            $values=do_language($ia);
            if (count($values[1]) > 1)
            {
                $question_text['help'] = $message = gT('Choose your language');
                $qtitle .= Yii::app()->getController()->renderPartial('/survey/question_help/help', array('message'=>$message, 'classes'=>''), true);
            }
            break;

        case 'P': //Multiple choice with comments checkbox + text
            $values=do_multiplechoice_withcomments($ia);
            if (count($values[1]) > 1 && $aQuestionAttributes['hide_tip']==0)
            {
                $maxansw=trim($aQuestionAttributes["max_answers"]);
                $minansw=trim($aQuestionAttributes["min_answers"]);
                if (!($maxansw || $minansw))
                {
                    $question_text['help'] = $message = gT('Check any that apply');
                    $qtitle .= Yii::app()->getController()->renderPartial('/survey/question_help/help', array('message'=>$message, 'classes'=>''), true);
                }
            }
            break;

        case '|': //File Upload
            $values=do_file_upload($ia);
            break;

        case 'Q': //MULTIPLE SHORT TEXT
            $values=do_multipleshorttext($ia);
            break;

        case 'K': //MULTIPLE NUMERICAL QUESTION
            $values=do_multiplenumeric($ia);
            break;

        case 'N': //NUMERICAL QUESTION TYPE
            $values=do_numerical($ia);
            break;

        case 'S': //SHORT FREE TEXT
            $values=do_shortfreetext($ia);
            break;

        case 'T': //LONG FREE TEXT
            $values=do_longfreetext($ia);
            break;

        case 'U': //HUGE FREE TEXT
            $values=do_hugefreetext($ia);
            break;

        case 'Y': //YES/NO radio-buttons
            $values=do_yesno($ia);
            break;

        case 'G': //GENDER drop-down list
            $values=do_gender($ia);
            break;

        case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
            $values=do_array_5point($ia);
            break;

        case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
            $values=do_array_10point($ia);
            break;

        case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
            $values=do_array_yesnouncertain($ia);
            break;

        case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
            $values=do_array_increasesamedecrease($ia);
            break;

        case 'F': //ARRAY (Flexible) - Row Format
            $values=do_array($ia);
            break;

        case 'H': //ARRAY (Flexible) - Column Format
            $values=do_arraycolumns($ia);
            break;

        case ':': //ARRAY (Multi Flexi) 1 to 10
            $values=do_array_multiflexi($ia);
            break;

        case ';': //ARRAY (Multi Flexi) Text
            $values=do_array_multitext($ia);  //It's like the "5th element" movie, come to life
            break;

        case '1': //Array (Flexible Labels) dual scale
            $values=do_array_dual($ia);
            break;

        case '*': // Equation
            $values=do_equation($ia);
            break;
    }


    if (isset($values)) //Break apart $values array returned from switch
    {
        //$answer is the html code to be printed
        //$inputnames is an array containing the names of each input field
        list($answer, $inputnames)=$values;
    }

    if ($ia[6] == 'Y')
    {
        $qtitle = Yii::app()->getController()->renderPartial('/survey/question_help/asterisk', array(), true);
        $qtitle .= $qtitle;
        $question_text['mandatory'] = gT('*');
    }

    //If this question is mandatory but wasn't answered in the last page
    //add a message HIGHLIGHTING the question
    $mandatory_msg = (($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['step'] != $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['maxstep']) || ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['step'] == $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['prevstep']))?mandatory_message($ia):'';
    $qtitle .= $mandatory_msg;
    $question_text['man_message'] = $mandatory_msg;

    $_vshow = (!isset($aQuestionAttributes['hide_tip']) || $aQuestionAttributes['hide_tip']==0)?true:false; // whether should initially be visible - TODO should also depend upon 'hidetip'?

    list($validation_msg,$isValid) = validation_message($ia,$_vshow);

    $qtitle .= $validation_msg;
    $question_text['valid_message'] = $validation_msg;

    if (($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['step'] != $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['maxstep']) || ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['step'] == $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['prevstep']))
    {
        $file_validation_msg = file_validation_message($ia);
    }
    else
    {
        $file_validation_msg = '';
        $isValid = true;    // don't want to show any validation messages.
    }

    $qtitle .= $ia[4] == "|" ? $file_validation_msg : "";
    $question_text['file_valid_message'] = $ia[4] == "|" ? $file_validation_msg : "";

    if (!empty($question_text['man_message']) || !$isValid || !empty($question_text['file_valid_message']))
    {
        $question_text['input_error_class'] = ' input-error';// provides a class to style question wrapper differently if there is some kind of user input error;
    }

    // =====================================================
    // START: legacy question_start.pstpl code
    // The following section adds to the templating system by allowing
    // templaters to control where the various parts of the question text
    // are put.

    $sTemplate = isset($thissurvey['template']) ? $thissurvey['template'] : NULL;
    if (is_file('templates/'.$sTemplate.'/question_start.pstpl'))
    {
        $qtitle_custom = '';

        $replace=array();
        foreach ($question_text as $key => $value)
        {
            $find[] = '{QUESTION_'.strtoupper($key).'}'; // Match key words from template
            $replace[] = $value; // substitue text
        };

        if (!defined('QUESTION_START'))
        {
            define('QUESTION_START' , file_get_contents(getTemplatePath($thissurvey['template']).'/question_start.pstpl' , true));
        };

        $qtitle_custom = str_replace( $find , $replace , QUESTION_START);

        $c = 1;
        // START: <EMBED> work-around step 1
        $qtitle_custom = preg_replace( '/(<embed[^>]+>)(<\/embed>)/i' , '\1NOT_EMPTY\2' , $qtitle_custom );
        // END <EMBED> work-around step 1
        while ($c > 0) // This recursively strips any empty tags to minimise rendering bugs.
        {
            $matches = 0;
            $oldtitle=$qtitle_custom;
            $qtitle_custom = preg_replace( '/<([^ >]+)[^>]*>[\r\n\t ]*<\/\1>[\r\n\t ]*/isU' , '' , $qtitle_custom , -1); // I removed the $count param because it is PHP 5.1 only.

            $c = ($qtitle_custom!=$oldtitle)?1:0;
        };
        // START <EMBED> work-around step 2
        $qtitle_custom = preg_replace( '/(<embed[^>]+>)NOT_EMPTY(<\/embed>)/i' , '\1\2' , $qtitle_custom );
        // END <EMBED> work-around step 2
        while ($c > 0) // This recursively strips any empty tags to minimise rendering bugs.
        {
            $matches = 0;
            $oldtitle=$qtitle_custom;
            $qtitle_custom = preg_replace( '/(<br(?: ?\/)?>(?:&nbsp;|\r\n|\n\r|\r|\n| )*)+$/i' , '' , $qtitle_custom , -1 ); // I removed the $count param because it is PHP 5.1 only.
            $c = ($qtitle_custom!=$oldtitle)?1:0;
        };

        $question_text['all'] = $qtitle_custom;
    }
    else
    {
        $question_text['all'] = $qtitle;
    };
    // END: legacy question_start.pstpl code
    //===================================================================
    $qtitle = $question_text;
    // =====================================================

    $qanda=array($qtitle, $answer, 'help', $display, $name, $ia[2], $ia[5], $ia[1] );
    //New Return
    return array($qanda, $inputnames);
}

function mandatory_message($ia)
{
    $qinfo = LimeExpressionManager::GetQuestionStatus($ia[0]);
    $qinfoValue = ($qinfo['mandViolation'])?$qinfo['mandTip']:"";
    return $qinfoValue;
}

/**
*
* @param <type> $ia
* @param <type> $show - true if should initially be visible
* @return <type>
*/
function validation_message($ia,$show)
{
    $qinfo      = LimeExpressionManager::GetQuestionStatus($ia[0]);
    $class      = (!$show)?' hide-tip':'';
    $id         = "vmsg_".$ia[0];
    $message    = $qinfo['validTip'];
    $tip = Yii::app()->getController()->renderPartial('/survey/question_help/help', array('message'=>$message, 'classes'=>$class, 'id'=>$id ), true);
    $isValid = $qinfo['valid'];
    return array($tip,$isValid);
}

// TMSW Validation -> EM
function file_validation_message($ia)
{
    global $filenotvalidated;
    $qtitle = "";
    if (isset($filenotvalidated) && is_array($filenotvalidated) && $ia[4] == "|")
    {
        global $filevalidationpopup, $popup;
        foreach ($filenotvalidated as $k => $v)
        {
            if ($ia[1] == $k || strpos($k, "_") && $ia[1] == substr(0, strpos($k, "_") - 1))
            {
                $message = gT($filenotvalidated[$k]);
                $qtitle .=  Yii::app()->getController()->renderPartial('/survey/question_help/error', array('message'=>$message, 'classes'=>''), true);
            }
        }
    }
    return $qtitle;
}

// TMSW Validation -> EM
function mandatory_popup($ia, $notanswered=null)
{
    //This sets the mandatory popup message to show if required
    //Called from question.php, group.php or survey.php
    if ($notanswered === null) {unset($notanswered);}
    if (isset($notanswered) && is_array($notanswered)) //ADD WARNINGS TO QUESTIONS IF THEY WERE MANDATORY BUT NOT ANSWERED
    {
        global $mandatorypopup, $popup;
        //POPUP WARNING
        if (!isset($mandatorypopup) && ($ia[4] == 'T' || $ia[4] == 'S' || $ia[4] == 'U'))
        {
            $popup=gT("You cannot proceed until you enter some text for one or more questions.");
            $mandatorypopup="Y";
        }
        else
        {
            $popup=gT("One or more mandatory questions have not been answered. You cannot proceed until these have been completed.");
            $mandatorypopup="Y";
        }
        return array($mandatorypopup, $popup);
    }
    else
    {
        return false;
    }
}

// TMSW Validation -> EM
function validation_popup($ia, $notvalidated=null)
{
    //This sets the validation popup message to show if required
    //Called from question.php, group.php or survey.php
    if ($notvalidated === null) {unset($notvalidated);}
    $qtitle="";
    if (isset($notvalidated) && is_array($notvalidated) )  //ADD WARNINGS TO QUESTIONS IF THEY ARE NOT VALID
    {
        global $validationpopup, $vpopup;
        //POPUP WARNING
        if (!isset($validationpopup))
        {
            $vpopup=gT("One or more questions have not been answered in a valid manner. You cannot proceed until these answers are valid.");
            $validationpopup="Y";
        }
        return array($validationpopup, $vpopup);
    }
    else
    {
        return false;
    }
}

// TMSW Validation -> EM
function file_validation_popup($ia, $filenotvalidated = null)
{

    if ($filenotvalidated === null) { unset($filenotvalidated); }
    if (isset($filenotvalidated) && is_array($filenotvalidated))
    {
        global $filevalidationpopup, $fpopup;

        if (!isset($filevalidationpopup))
        {
            $fpopup=gT("One or more file have either exceeded the filesize/are not in the right format or the minimum number of required files have not been uploaded. You cannot proceed until these have been completed");
            $filevalidationpopup = "Y";
        }
        return array($filevalidationpopup, $fpopup);
    }
    else
        return false;
}

function return_timer_script($aQuestionAttributes, $ia, $disable=null)
{
    global $thissurvey;
    Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig("generalscripts").'coookies.js');

    /**
     * The following lines cover for previewing questions, because no $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['fieldarray'] exists.
     * This just stops error messages occuring
     */
    if (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['fieldarray']))
    {
        $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['fieldarray'] = array();
    }
    /* End */

    //Used to count how many timer questions in a page, and ensure scripts only load once
    $thissurvey['timercount'] = (isset($thissurvey['timercount']))?$thissurvey['timercount']++:1;

    if ($thissurvey['format'] != "S")
    {
        if ($thissurvey['format'] != "G")
        {
            return "\n\n<!-- TIMER MODE DISABLED DUE TO INCORRECT SURVEY FORMAT -->\n\n";
            //We don't do the timer in any format other than question-by-question
        }
    }

    $time_limit=$aQuestionAttributes['time_limit'];
    $disable_next=trim($aQuestionAttributes['time_limit_disable_next']) != '' ? $aQuestionAttributes['time_limit_disable_next'] : 0;
    $disable_prev=trim($aQuestionAttributes['time_limit_disable_prev']) != '' ? $aQuestionAttributes['time_limit_disable_prev'] : 0;
    $time_limit_action=trim($aQuestionAttributes['time_limit_action']) != '' ? $aQuestionAttributes['time_limit_action'] : 1;
    $time_limit_message_delay=trim($aQuestionAttributes['time_limit_message_delay']) != '' ? $aQuestionAttributes['time_limit_message_delay']*1000 : 1000;
    $time_limit_message=trim($aQuestionAttributes['time_limit_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']]) != '' ? htmlspecialchars($aQuestionAttributes['time_limit_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']], ENT_QUOTES) : gT("Your time to answer this question has expired");
    $time_limit_warning=trim($aQuestionAttributes['time_limit_warning']) != '' ? $aQuestionAttributes['time_limit_warning'] : 0;
    $time_limit_warning_2=trim($aQuestionAttributes['time_limit_warning_2']) != '' ? $aQuestionAttributes['time_limit_warning_2'] : 0;
    $time_limit_countdown_message=trim($aQuestionAttributes['time_limit_countdown_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']]) != '' ? htmlspecialchars($aQuestionAttributes['time_limit_countdown_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']], ENT_QUOTES) : gT("Time remaining");
    $time_limit_warning_message=trim($aQuestionAttributes['time_limit_warning_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']]) != '' ? htmlspecialchars($aQuestionAttributes['time_limit_warning_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']], ENT_QUOTES) : gT("Your time to answer this question has nearly expired. You have {TIME} remaining.");

    //Render timer
    $timer_html =  Yii::app()->getController()->renderPartial('/survey/question_timer/timer', array('iQid'=>$ia[0], 'sWarnId'=>''), true);
    $time_limit_warning_message=str_replace("{TIME}", $timer_html, $time_limit_warning_message);
    $time_limit_warning_display_time=trim($aQuestionAttributes['time_limit_warning_display_time']) != '' ? $aQuestionAttributes['time_limit_warning_display_time']+1 : 0;
    $time_limit_warning_2_message=trim($aQuestionAttributes['time_limit_warning_2_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']]) != '' ? htmlspecialchars($aQuestionAttributes['time_limit_warning_2_message'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']], ENT_QUOTES) : gT("Your time to answer this question has nearly expired. You have {TIME} remaining.");

    //Render timer 2
    $timer_html =  Yii::app()->getController()->renderPartial('/survey/question_timer/timer', array('iQid'=>$ia[0], 'sWarnId'=>'_Warning_2'), true);
    $time_limit_warning_2_message=str_replace("{TIME}", $timer_html, $time_limit_warning_2_message);
    $time_limit_warning_2_display_time=trim($aQuestionAttributes['time_limit_warning_2_display_time']) != '' ? $aQuestionAttributes['time_limit_warning_2_display_time']+1 : 0;
    $time_limit_message_style=trim($aQuestionAttributes['time_limit_message_style']) != '' ? $aQuestionAttributes['time_limit_message_style'] : "";
    $time_limit_message_style.="\n        display: none;"; //Important to hide time limit message at start
    $time_limit_warning_style=trim($aQuestionAttributes['time_limit_warning_style']) != '' ? $aQuestionAttributes['time_limit_warning_style'] : "";
    $time_limit_warning_style.="\n        display: none;"; //Important to hide time limit warning at the start
    $time_limit_warning_2_style=trim($aQuestionAttributes['time_limit_warning_2_style']) != '' ? $aQuestionAttributes['time_limit_warning_2_style'] : "";
    $time_limit_warning_2_style.="\n        display: none;"; //Important to hide time limit warning at the start
    $time_limit_timer_style=trim($aQuestionAttributes['time_limit_timer_style']) != '' ? $aQuestionAttributes['time_limit_timer_style'] : "position: relative;";

    $timersessionname="timer_question_".$ia[0];
    if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$timersessionname]))
    {
        $time_limit=$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$timersessionname];
    }

    $output =  Yii::app()->getController()->renderPartial('/survey/question_timer/timer_header', array('timersessionname'=>$timersessionname,'timersessionname'=>$timersessionname,'timersessionname'=>$timersessionname,'time_limit'=>$time_limit), true);

    if ($thissurvey['timercount'] < 2)
    {
        $iAction = '';
        if (isset($thissurvey['format']) && $thissurvey['format'] == "G")
        {
            global $gid;
            $qcount=0;
            foreach ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['fieldarray'] as $ib)
            {
                if ($ib[5] == $gid)
                {
                    $qcount++;
                }
            }
            // Override all other options and just allow freezing, survey is presented in group by group mode
            // Why don't allow submit in Group by group mode, this surely broke 'mandatory' question, but this remove a great system for user (Denis 140224)
            if ($qcount > 1)
            {
                $iAction = '3';
            }
        }

        /* If this is a preview, don't allow the page to submit/reload */
        $thisaction=returnglobal('action');
        if($thisaction == "previewquestion" || $thisaction == "previewgroup")
        {
            $iAction = '3';
        }

        $output .=  Yii::app()->getController()->renderPartial('/survey/question_timer/timer_javascript', array('iAction'=>$iAction, 'disable_next'=>$disable_next, 'disable_prev'=>$disable_prev, 'time_limit_countdown_message' =>$time_limit_countdown_message ), true);

    }

    $output .=  Yii::app()->getController()->renderPartial(
                    '/survey/question_timer/timer_content',
                    array(
                            'iQid'=>$ia[0],
                            'time_limit_message_style'=>$time_limit_message_style,
                            'time_limit_message'=>$time_limit_message,
                            'time_limit_warning_style'=>$time_limit_warning_style,
                            'time_limit_warning_message'=>$time_limit_warning_message,
                            'time_limit_warning_2_style'=>$time_limit_warning_2_style,
                            'time_limit_warning_2_message'=>$time_limit_warning_2_message,
                            'time_limit_timer_style'=>$time_limit_timer_style,
                        ),
                    true
                );

    $output .=  Yii::app()->getController()->renderPartial(
                    '/survey/question_timer/timer_footer',
                    array(
                            'iQid'=>$ia[0],
                            'time_limit'=>$time_limit,
                            'time_limit_action'=>$time_limit_action,
                            'time_limit_warning'=>$time_limit_warning,
                            'time_limit_warning_2'=>$time_limit_warning_2,
                            'time_limit_warning_display_time'=>$time_limit_warning_display_time,
                            'time_limit_warning_display_time'=>$time_limit_warning_display_time,
                            'time_limit_warning_2_display_time'=>$time_limit_warning_2_display_time,
                            'disable'=>$disable,
                        ),
                true);
    return $output;
}

function return_display_style($ia, $aQuestionAttributes, $thissurvey, $rowname)
{
    $htmltbody2 = '';
    $surveyid=$thissurvey['sid'];
    if (isset($_SESSION["survey_{$surveyid}"]['relevanceStatus'][$rowname]) && !$_SESSION["survey_{$surveyid}"]['relevanceStatus'][$rowname])
    {
        // If using exclude_all_others, then need to know whether irrelevant rows should be hidden or disabled
        if (isset($aQuestionAttributes['exclude_all_others']))
        {
            $disableit=false;
            foreach(explode(';',trim($aQuestionAttributes['exclude_all_others'])) as $eo)
            {
                $eorow = $ia[1] . $eo;
                if ((!isset($_SESSION["survey_{$surveyid}"]['relevanceStatus'][$eorow]) || $_SESSION["survey_{$surveyid}"]['relevanceStatus'][$eorow])
                    && (isset($_SESSION[$eorow]) && $_SESSION[$eorow] == "Y"))
                {
                    $disableit = true;
                }
            }
            if ($disableit)
            {
                $htmltbody2 .= " disabled='disabled'";
            }
            else
            {
                if (!isset($aQuestionAttributes['array_filter_style']) || $aQuestionAttributes['array_filter_style'] == '0')
                {
                    $htmltbody2 .= " style='display: none'";
                }
                else
                {
                    $htmltbody2 .= " disabled='disabled'";
                }
            }
        }
        else
        {
            if (!isset($aQuestionAttributes['array_filter_style']) || $aQuestionAttributes['array_filter_style'] == '0')
            {
                $htmltbody2 .= " style='display: none'";
            }
            else
            {
                $htmltbody2 .= " disabled='disabled'";
            }
        }
    }

    return $htmltbody2;
}

function return_array_filter_strings($ia, $aQuestionAttributes, $thissurvey, $ansrow, $rowname, $trbc='', $valuename, $method="tbody", $class=null)
{
    $htmltbody2 = "\n\n\t<$method id='javatbd$rowname'";
    $htmltbody2 .= ($class !== null) ? " class='$class'": "";
    $surveyid=$thissurvey['sid'];
    if (isset($_SESSION["survey_{$surveyid}"]['relevanceStatus'][$rowname]) && !$_SESSION["survey_{$surveyid}"]['relevanceStatus'][$rowname])
    {
        // If using exclude_all_others, then need to know whether irrelevant rows should be hidden or disabled
        if (isset($aQuestionAttributes['exclude_all_others']))
        {
            $disableit=false;
            foreach(explode(';',trim($aQuestionAttributes['exclude_all_others'])) as $eo)
            {
                $eorow = $ia[1] . $eo;
                if ((!isset($_SESSION["survey_{$surveyid}"]['relevanceStatus'][$eorow]) || $_SESSION["survey_{$surveyid}"]['relevanceStatus'][$eorow])
                    && (isset($_SESSION[$eorow]) && $_SESSION[$eorow] == "Y"))
                {
                    $disableit = true;
                }
            }
            if ($disableit)
            {
                $htmltbody2 .= " disabled='disabled'";
            }
            else
            {
                if (!isset($aQuestionAttributes['array_filter_style']) || $aQuestionAttributes['array_filter_style'] == '0')
                {
                    $htmltbody2 .= " style='display: none'";
                }
                else
                {
                    $htmltbody2 .= " disabled='disabled'";
                }
            }
        }
        else
        {
            if (!isset($aQuestionAttributes['array_filter_style']) || $aQuestionAttributes['array_filter_style'] == '0')
            {
                $htmltbody2 .= " style='display: none'";
            }
            else
            {
                $htmltbody2 .= " disabled='disabled'";
            }
        }
    }
    $htmltbody2 .= ">\n";
    return array($htmltbody2, "");
}

// ==================================================================
// setting constants for 'checked' and 'selected' inputs
define('CHECKED' , ' checked="checked"' , true);
define('SELECTED' , ' selected="selected"' , true);

// ==================================================================
// QUESTION METHODS =================================================

function do_boilerplate($ia)
{
    //$aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $answer='';

    if (trim($aQuestionAttributes['time_limit'])!='')
    {
        $answer .= return_timer_script($aQuestionAttributes, $ia);
    }

    $answer .= Yii::app()->getController()->renderPartial('/survey/questions/boilerplate/answer', array('ia'=>$ia), true);
    $inputnames[]=$ia[1];

    return array($answer, $inputnames);
}

function do_equation($ia)
{
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $sEquation           = (trim($aQuestionAttributes['equation'])) ? $aQuestionAttributes['equation'] : $ia[3];
    $sValue              = htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],ENT_QUOTES);

    $answer       = Yii::app()->getController()->renderPartial('/survey/questions/equation/answer', array(
        'name'      => $ia[1],
        'sValue'    => $sValue,
        'sEquation' => $sEquation,
    ), true);

    $inputnames[] = $ia[1];
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_5pointchoice($ia)
{
    $imageurl = Yii::app()->getConfig("imageurl");
    $checkconditionFunction = "checkconditions";
    //$aQuestionAttributes=  getQuestionAttributeValues($ia[0]);
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $id = 'slider'.time().rand(0,100);

    $sRows = "";
    for ($fp=1; $fp<=5; $fp++)
    {
        $checkedState = '';
        if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == $fp)
        {
            //$answer .= CHECKED;
            $checkedState = ' CHECKED ';
        }

        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/5pointchoice/rows/item_row', array(
            'name'                   => $ia[1],
            'value'                  => $fp,
            'id'                     => $ia[1].$fp,
            'labelText'              => $fp,
            'itemExtraClass'         => 'col-md-1',
            'checkedState'           => $checkedState,
            'checkconditionFunction' => $checkconditionFunction,
        ), true);
    }

    if ($ia[6] != "Y"  && SHOW_NO_ANSWER == 1) // Add "No Answer" option if question is not mandatory
    {
        $checkedState = '';
        if (!$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]])
        {
            $checkedState = ' CHECKED ';
        }
        $aData = array(
            'name'                   => $ia[1],
            'value'                  => $fp,
            'id'                     => $ia[1].'NANS',
            'labelText'              => gT('No answer'),
            'itemExtraClass'         => 'noanswer-item',
            'checkedState'           => $checkedState,
            'checkconditionFunction' => $checkconditionFunction,
        );
        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/5pointchoice/rows/item_row', $aData, true);

    }
    $sessionValue = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]];

    $inputnames[]=$ia[1];

    $slider_rating = 0;

    if($aQuestionAttributes['slider_rating']==1){
        $slider_rating = 1;
        Yii::app()->getClientScript()->registerCssFile(Yii::app()->getConfig('publicstyleurl') . 'star-rating.css');
        Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."star-rating.js");
    }

    if($aQuestionAttributes['slider_rating']==2){
        $slider_rating = 2;
        Yii::app()->getClientScript()->registerCssFile(Yii::app()->getConfig('publicstyleurl') . 'slider-rating.css');
        Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."slider-rating.js");
    }


    $answer = Yii::app()->getController()->renderPartial('/survey/questions/5pointchoice/answer', array(
        'id'            => $id,
        'sliderId'      => $ia[0],
        'name'          => $ia[1],
        'sessionValue'  => $sessionValue,
        'sRows'         => $sRows,
        'slider_rating' => $slider_rating,
    ), true);

    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_date($ia)
{
    global $thissurvey;
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $checkconditionFunction = "checkconditions";
    $dateformatdetails      = getDateFormatDataForQID($aQuestionAttributes,$thissurvey);
    $numberformatdatat      = getRadixPointData($thissurvey['surveyls_numberformat']);

    $sDateLangvarJS         = " translt = {
         alertInvalidDate: '" . gT('Date entered is invalid!','js') . "',
        };";

    App()->getClientScript()->registerScript("sDateLangvarJS",$sDateLangvarJS,CClientScript::POS_HEAD);
    App()->getClientScript()->registerScriptFile(Yii::app()->getConfig("generalscripts").'date.js');
    App()->getClientScript()->registerScriptFile(Yii::app()->getConfig("third_party").'jstoolbox/date.js');

    // date_min: Determine whether we have an expression, a full date (YYYY-MM-DD) or only a year(YYYY)
    if (trim($aQuestionAttributes['date_min'])!='')
    {
        $date_min      = trim($aQuestionAttributes['date_min']);
        $date_time_em  = strtotime(LimeExpressionManager::ProcessString("{".$date_min."}",$ia[0]));

        if (ctype_digit($date_min) && (strlen($date_min)==4) && ($date_min>=1900) && ($date_min<=2099))
        {
            $mindate = $date_min.'-01-01'; // backward compatibility: if only a year is given, add month and day
        }
        elseif (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])/",$date_min))// it's a YYYY-MM-DD date (use http://www.yiiframework.com/doc/api/1.1/CDateValidator ?)
        {
            $mindate = $date_min;
        }
        elseif ($date_time_em)
        {
            $mindate = date("Y-m-d",$date_time_em);
        }
        else
        {
            $mindate = '{'.$aQuestionAttributes['date_min'].'}';
        }
    }
    else
    {
        $mindate = '1900-01-01'; // Why 1900 ?
    }

    // date_max: Determine whether we have an expression, a full date (YYYY-MM-DD) or only a year(YYYY)
    if (trim($aQuestionAttributes['date_max'])!='')
    {
        $date_max     = trim($aQuestionAttributes['date_max']);
        $date_time_em = strtotime(LimeExpressionManager::ProcessString("{".$date_max."}",$ia[0]));

        if (ctype_digit($date_max) && (strlen($date_max)==4) && ($date_max>=1900) && ($date_max<=2099))
        {
            $maxdate = $date_max.'-12-31'; // backward compatibility: if only a year is given, add month and day
        }
        elseif (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])/",$date_max))// it's a YYYY-MM-DD date (use http://www.yiiframework.com/doc/api/1.1/CDateValidator ?)
        {
            $maxdate = $date_max;
        }
        elseif($date_time_em)
        {
            $maxdate = date("Y-m-d",$date_time_em);
        }
        else
        {
            $maxdate = '{'.$aQuestionAttributes['date_max'].'}';
        }
    }
    else
    {
        $maxdate = '2037-12-31'; // Why 2037 ?
    }

    if (trim($aQuestionAttributes['dropdown_dates'])==1)
    {
        if (!empty($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]) &&
           ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]!='INVALID'))
        {
            $datetimeobj   = new Date_Time_Converter($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]], "Y-m-d H:i:s");
            $currentyear   = $datetimeobj->years;
            $currentmonth  = $datetimeobj->months;
            $currentdate   = $datetimeobj->days;
            $currenthour   = $datetimeobj->hours;
            $currentminute = $datetimeobj->minutes;
        }
        else
        {
            // If date is invalid get the POSTED value
            $currentdate   = App()->request->getPost("day{$ia[1]}",'');
            $currentmonth  = App()->request->getPost("month{$ia[1]}",'');
            $currentyear   = App()->request->getPost("year{$ia[1]}",'');
            $currenthour   = App()->request->getPost("hour{$ia[1]}",'');
            $currentminute = App()->request->getPost("minute{$ia[1]}",'');
        }

        $dateorder = preg_split('/([-\.\/ :])/', $dateformatdetails['phpdate'],-1,PREG_SPLIT_DELIM_CAPTURE );

        $sRows = '';
        foreach($dateorder as $datepart)
        {
            switch($datepart)
            {
                // Show day select box
                case 'j':
                case 'd':
                    $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/date/dropdown/rows/day', array('dayId'=>$ia[1], 'currentdate'=>$currentdate), true);
                    break;
                    // Show month select box
                case 'n':
                case 'm':
                    switch ((int)trim($aQuestionAttributes['dropdown_dates_month_style']))
                    {
                        case 0:
                            $montharray=array(
                             gT('Jan'),
                             gT('Feb'),
                             gT('Mar'),
                             gT('Apr'),
                             gT('May'),
                             gT('Jun'),
                             gT('Jul'),
                             gT('Aug'),
                             gT('Sep'),
                             gT('Oct'),
                             gT('Nov'),
                             gT('Dec'));
                             break;
                        case 1:
                            $montharray=array(
                             gT('January'),
                             gT('February'),
                             gT('March'),
                             gT('April'),
                             gT('May'),
                             gT('June'),
                             gT('July'),
                             gT('August'),
                             gT('September'),
                             gT('October'),
                             gT('November'),
                             gT('December'));
                             break;
                        case 2:
                            $montharray=array('01','02','03','04','05','06','07','08','09','10','11','12');
                            break;
                    }

                    $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/date/dropdown/rows/month', array('monthId'=>$ia[1], 'currentmonth'=>$currentmonth, 'montharray'=>$montharray), true);
                    break;
                    // Show year select box
                case 'y':
                case 'Y':
                    /*
                    * yearmin = Minimum year value for dropdown list, if not set default is 1900
                    * yearmax = Maximum year value for dropdown list, if not set default is 2037
                    * if full dates (format: YYYY-MM-DD) are given, only the year is used
                    * expressions are not supported because contents of dropbox cannot be easily updated dynamically
                    */
                    $yearmin = (int)substr($mindate,0,4);
                    if (!isset($yearmin) || $yearmin<1900 || $yearmin>2037)
                    {
                        $yearmin = 1900;
                    }

                    $yearmax = (int)substr($maxdate, 0, 4);
                    if (!isset($yearmax) || $yearmax<1900 || $yearmax>2037)
                    {
                        $yearmax = 2037;
                    }

                    if ($yearmin > $yearmax)
                    {
                        $yearmin = 1900;
                        $yearmax = 2037;
                    }

                    if ($aQuestionAttributes['reverse']==1)
                    {
                        $tmp = $yearmin;
                        $yearmin = $yearmax;
                        $yearmax = $tmp;
                        $step = 1;
                        $reverse = true;
                    }
                    else
                    {
                        $step = -1;
                        $reverse = false;
                    }
                    $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/date/dropdown/rows/year', array('yearId'=>$ia[1], 'currentyear'=>$currentyear,'yearmax'=>$yearmax,'reverse'=>$reverse,'yearmin'=>$yearmin,'step'=>$step), true);
                    break;
                case 'H':
                case 'h':
                case 'g':
                case 'G':
                    $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/date/dropdown/rows/hour', array('hourId'=>$ia[1], 'currenthour'=>$currenthour, 'datepart'=>$datepart), true);
                    break;
                case 'i':
                    $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/date/dropdown/rows/minute', array('minuteId'=>$ia[1], 'currentminute'=>$currenthour, 'dropdown_dates_minute_step'=>$aQuestionAttributes['dropdown_dates_minute_step'], 'datepart'=>$datepart ), true);
                    break;
                default:
                $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/date/dropdown/rows/datepart', array('datepart'=>$datepart ), true);

            }
        }

        // Format the date  for output
        $dateoutput=trim($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]);
        if ($dateoutput!='' & $dateoutput!='INVALID')
        {
            $datetimeobj = new Date_Time_Converter($dateoutput , "Y-m-d H:i");
            $dateoutput = $datetimeobj->convert($dateformatdetails['phpdate']);
        }


        // ==> answer
        $answer    = Yii::app()->getController()->renderPartial('/survey/questions/date/dropdown/answer', array(
            'sRows'                  => $sRows,
            'name'                   => $ia[1],
            'dateoutput'             => htmlspecialchars($dateoutput,ENT_QUOTES,'utf-8'),
            'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
            'dateformatdetails'      => $dateformatdetails['jsdate'],
            'dateformat'             => $dateformatdetails['dateformat'],
        ), true);

        App()->getClientScript()->registerScript("doDropDownDate{$ia[0]}","doDropDownDate({$ia[0]});",CClientScript::POS_HEAD);
    }
    else
    {
        //register timepicker extension
        App()->getClientScript()->registerPackage('jqueryui-timepicker');

        // Locale for datepicker and timpicker extension
        if (App()->language !== 'en')
        {
            Yii::app()->getClientScript()->registerScriptFile(App()->getConfig('third_party')."/jqueryui/development-bundle/ui/i18n/jquery.ui.datepicker-".App()->language.".js");
            Yii::app()->getClientScript()->registerScriptFile(App()->getConfig('third_party')."/jquery-ui-timepicker-addon/i18n/jquery-ui-timepicker-".App()->language.".js");
        }
        // Format the date  for output
        $dateoutput = trim($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]);
        if ($dateoutput!='' & $dateoutput!='INVALID')
        {
            $datetimeobj = new Date_Time_Converter($dateoutput , "Y-m-d H:i");
            $dateoutput  = $datetimeobj->convert($dateformatdetails['phpdate']);
        }

        $goodchars = str_replace( array("m","d","y"), "", $dateformatdetails['jsdate']);
        $goodchars = "0123456789".substr($goodchars,0,1);
        // Max length of date : Get the date of 1999-12-30 at 32:59:59 to be sure to have space with non leading 0 format
        // "+1" makes room for a trailing space in date/time values
        $iLength   = strlen(date($dateformatdetails['phpdate'],mktime(23,59,59,12,30,1999)))+1;

        // HTML for date question using datepicker
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/date/selector/answer', array(
            'name'                   => $ia[1],
            'iLength'                => $iLength,
            'mindate'                => $mindate,
            'maxdate'                => $maxdate,
            'dateformatdetails'      => $dateformatdetails['dateformat'],
            'dateformatdetailsjs'    => $dateformatdetails['jsdate'],
            'goodchars'              => "return goodchars(event,'".$goodchars."')",
            'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
            'language'               => App()->language,
            'hidetip'                => trim($aQuestionAttributes['hide_tip'])==0,
            'dateoutput'             => $dateoutput,
            'qid'                    => $ia[0],
        ), true);
    }
    $inputnames[]=$ia[1];

    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_language($ia)
{
    $checkconditionFunction = "checkconditions";
    $answerlangs            = Survey::model()->findByPk(Yii::app()->getConfig('surveyID'))->additionalLanguages;
    $answerlangs[]          = Survey::model()->findByPk(Yii::app()->getConfig('surveyID'))->language;
    $sLang                  = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang'];

    if (!in_array($sLang,$answerlangs))
    {
        $sLang = Survey::model()->findByPk(Yii::app()->getConfig('surveyID'))->language;
    }

    $inputnames[] = $ia[1];

    $languageData = array(
        'name'=>$ia[1],
        'checkconditionFunction'=>$checkconditionFunction.'(this.value, this.name, this.type)',
        'answerlangs'=>$answerlangs,
        'sLang'=>$sLang,
    );

    $answer  = Yii::app()->getController()->renderPartial('/survey/questions/language/answer', $languageData, true);
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
// TMSW TODO - Can remove DB query by passing in answer list from EM
function do_list_dropdown($ia)
{
    //// Init variables

    // General variables
    $checkconditionFunction = "checkconditions";

    // Question attribute variables
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $iSurveyId              = Yii::app()->getConfig('surveyID'); // survey id
    $sSurveyLang            = $_SESSION['survey_'.$iSurveyId]['s_lang']; // survey language
    $othertext              = (trim($aQuestionAttributes['other_replace_text'][$sSurveyLang])!='')?$aQuestionAttributes['other_replace_text'][$sSurveyLang]:gT('Other:'); // text for 'other'
    $optCategorySeparator   = (trim($aQuestionAttributes['category_separator'])!='')?$aQuestionAttributes['category_separator']:'';

    if ($optCategorySeparator=='')
    {
        unset($optCategorySeparator);
    }

    //// Retrieving datas

    // Getting question
    $oQuestion = Question::model()->findByPk(array('qid'=>$ia[0], 'language'=>$sSurveyLang));
    $other     = $oQuestion->other;

    // Getting answers
    $ansresult = $oQuestion->getOrderedAnswers($aQuestionAttributes['random_order'], $aQuestionAttributes['alphasort'] );

    $dropdownSize = '';

    if (isset($aQuestionAttributes['dropdown_size']) && $aQuestionAttributes['dropdown_size'] > 0)
    {
        $_height    = sanitize_int($aQuestionAttributes['dropdown_size']) ;
        $_maxHeight = count($ansresult);

        if ((!empty($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]])) && $ia[6] != 'Y' && $ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
        {
            ++$_maxHeight;  // for No Answer
        }

        if (isset($other) && $other=='Y')
        {
            ++$_maxHeight;  // for Other
        }

        if (!$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]])
        {
            ++$_maxHeight;  // for 'Please choose:'
        }

        if ($_height > $_maxHeight)
        {
            $_height = $_maxHeight;
        }
        $dropdownSize = ' size="'.$_height.'"';
    }

    $prefixStyle = 0;

    if (isset($aQuestionAttributes['dropdown_prefix']))
    {
        $prefixStyle = sanitize_int($aQuestionAttributes['dropdown_prefix']) ;
    }

    $_rowNum = 0;
    $_prefix = '';

    $value            = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]];
    $select_show_hide = (isset($other) && $other=='Y')?' showhideother(this.name, this.value);':'';
    $sOptions         = '';

    // If no answer previously selected
    if (!$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]])
    {
        $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/option', array(
            'value'=>'',
            'opt_select'=>'SELECTED',
            'answer'=>gT('Please choose...')
        ), true);
    }

    if (!isset($optCategorySeparator))
    {
        foreach ($ansresult as $ansrow)
        {
            $opt_select = '';
            if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == $ansrow['code'])
            {
                $opt_select = SELECTED;
            }
            if ($prefixStyle == 1)
            {
                $_prefix = ++$_rowNum . ') ';
            }
            // ==> rows
            $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/option', array(
                'value'=>$ansrow['code'],
                'opt_select'=>$opt_select,
                'answer'=>flattenText($_prefix.$ansrow['answer'])
            ), true);
        }
    }
    else
    {
        $defaultopts = Array();
        $optgroups = Array();
        foreach ($ansresult as $ansrow)
        {
            // Let's sort answers in an array indexed by subcategories
            @list ($categorytext, $answertext) = explode($optCategorySeparator,$ansrow['answer']);
            // The blank category is left at the end outside optgroups
            if ($categorytext == '')
            {
                $defaultopts[] = array ( 'code' => $ansrow['code'], 'answer' => $answertext);
            }
            else
            {
                $optgroups[$categorytext][] = array ( 'code' => $ansrow['code'], 'answer' => $answertext);
            }
        }

        foreach ($optgroups as $categoryname => $optionlistarray)
        {

            $sOptGroupOptions = '';
            foreach ($optionlistarray as $optionarray)
            {
                if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == $optionarray['code'])
                {
                    $opt_select = SELECTED;
                }
                else
                {
                    $opt_select = '';
                }

                // ==> rows
                $sOptGroupOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/option', array(
                    'value'=>$optionarray['code'],
                    'opt_select'=>$opt_select,
                    'answer'=>flattenText($optionarray['answer'])
                ), true);
            }


            $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/optgroup', array(
                'categoryname'      => flattenText($categoryname),
                'sOptGroupOptions'  => $sOptGroupOptions,
            ), true);
        }
        $opt_select='';
        foreach ($defaultopts as $optionarray)
        {
            if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == $optionarray['code'])
            {
                $opt_select = SELECTED;
            }
            else
            {
                $opt_select = '';
            }

            // ==> rows
            $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/option', array(
                'value'=>$optionarray['code'],
                'opt_select'=>$opt_select,
                'answer'=>flattenText($optionarray['answer'])
            ), true);
        }
    }

    if (isset($other) && $other=='Y')
    {
        if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == '-oth-')
        {
            $opt_select = SELECTED;
        }
        else
        {
            $opt_select = '';
        }
        if ($prefixStyle == 1) {
            $_prefix = ++$_rowNum . ') ';
        }

        $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/option', array(
            'value'=>'-oth-',
            'opt_select'=>$opt_select,
            'answer'=>flattenText($_prefix.$othertext)
        ), true);
    }

    if (($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] != '') && $ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
    {
        if ($prefixStyle == 1)
        {
            $_prefix = ++$_rowNum . ') ';
        }

        $optionData = array(
            'classes'=>'noanswer-item',
            'value'=>'',
            'opt_select'=>$opt_select,
            'answer'=>$_prefix.gT('No answer')
        );
        // ==> rows
        $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/option', $optionData, true);
    }

    $sOther = '';
    if (isset($other) && $other=='Y')
    {
        $aData = array();
        $aData['name']= $ia[1];
        $aData['checkconditionFunction']=$checkconditionFunction;
        $aData['display'] = ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] != '-oth-')?'display: none;':'';
        $thisfieldname="$ia[1]other";
        $aData['value'] = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$thisfieldname]))?htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$thisfieldname],ENT_QUOTES):'';

        // ==> other
        $sOther .= Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/rows/othertext', $aData, true);

        $inputnames[]=$ia[1].'other';

    }

    // ==> answer
    $answer = Yii::app()->getController()->renderPartial('/survey/questions/list_dropdown/answer', array(
        'sOptions'               => $sOptions,
        'sOther'                 => $sOther,
        'name'                   => $ia[1],
        'dropdownSize'           => $dropdownSize,
        'checkconditionFunction' => $checkconditionFunction,
        'value'                  => $value,
        'select_show_hide'       => $select_show_hide,
    ), true);


    $inputnames[]=$ia[1];

    //Time Limit Code
    if (trim($aQuestionAttributes['time_limit'])!='')
    {
        $sselect .= return_timer_script($aQuestionAttributes, $ia);
    }
    //End Time Limit Code

    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
// TMSW TODO - Can remove DB query by passing in answer list from EM

function do_list_radio($ia)
{
    //// Init variables

    // General variables
    global $dropdownthreshold;
    global $thissurvey;
    $kpclass                = testKeypad($thissurvey['nokeyboard']);                                             // Virtual keyboard (probably obsolete today)
    $checkconditionFunction = "checkconditions";                                                                 // name of the function to check condition TODO : check is used more than once
    $iSurveyId              = Yii::app()->getConfig('surveyID');                                                 // survey id
    $sSurveyLang            = $_SESSION['survey_'.$iSurveyId]['s_lang'];                                         // survey language

    // Question attribute variables

    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $othertext           = (trim($aQuestionAttributes['other_replace_text'][$sSurveyLang])!='')?$aQuestionAttributes['other_replace_text'][$sSurveyLang]:gT('Other:');  // text for 'other'
    $iNbCols             = (trim($aQuestionAttributes['display_columns'])!='')?$aQuestionAttributes['display_columns']:1;                                               // number of columns
    $sTimer              = (trim($aQuestionAttributes['time_limit'])!='')?return_timer_script($aQuestionAttributes, $ia):'';                                            //Time Limit
    //// Retrieving datas

    // Getting question
    $oQuestion = Question::model()->findByPk(array('qid'=>$ia[0], 'language'=>$sSurveyLang));
    $other     = $oQuestion->other;

    // Getting answers
    $ansresult = $oQuestion->getOrderedAnswers($aQuestionAttributes['random_order'], $aQuestionAttributes['alphasort'] );
    $anscount  = count($ansresult);
    $anscount  = ($other == 'Y') ? $anscount+1 : $anscount; //COUNT OTHER AS AN ANSWER FOR MANDATORY CHECKING!
    $anscount  = ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)  ? $anscount+1 : $anscount; //Count up if "No answer" is showing

    //// Columns containing answer rows, set by user in question attribute
    /// TODO : move to a dedicated function

    // setting variables
    $iMaxRowsByColumn = 0; // How many answer rows by column
    $iRowCount        = 0;
    $isOpen           = false;       // Is a column opened

    if ($iNbCols > 1)
    {
        // First we calculate the width of each column
        // Max number of column is 12 http://getbootstrap.com/css/#grid
        $iColumnWidth = round(12 / $iNbCols);
        $iColumnWidth = ($iColumnWidth >= 1 )?$iColumnWidth:1;
        $iColumnWidth = ($iColumnWidth <= 12)?$iColumnWidth:12;

        // Then, we calculate how many answer rows in each column
        $iMaxRowsByColumn = ceil($anscount / $iNbCols);
        $first = true; // The very first item will open a bootstrap row containing the columns
    }

    // Get array_filter stuff

    $i = 0;

    $sRows = '';
    foreach ($ansresult as $key=>$ansrow)
    {
        $i++; // general count of loop, to check if the item is the last one for column process. Never reset.
        $iRowCount++; // counter of number of row by column. Is reset to zero each time a column is full.
        $myfname = $ia[1].$ansrow['code'];

        $checkedState = '';
        if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == $ansrow['code'])
        {
            $checkedState = 'CHECKED';
        }

        //list($htmltbody2, $hiddenfield)=return_array_filter_strings($ia, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, '', $myfname, "div","form-group answer-item radio-item");
        /* Check for array_filter */
        $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

        ////
        // Open Column
        // The column is opened if user set more than one column in question attribute
        // and if this is the first answer row, or if the column has been closed and the row count reset before.
        if($iNbCols > 1 && $iRowCount == 1 )
        {
            $sRows  .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/columns/column_header', array('iColumnWidth' => $iColumnWidth), true);
            $isOpen  = true; // If a column is not closed, it will be closed at the end of the process
            $first   = false; // The row containing the column has been opened at the first call.
        }


        ////
        // Insert row
        // Display the answer row
        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/rows/answer_row', array(
            'sDisplayStyle' => $sDisplayStyle,
            'name'          => $ia[1],
            'code'          => $ansrow['code'],
            'answer'        => $ansrow['answer'],
            'checkedState'  => $checkedState,
            'myfname'       => $myfname,
        ), true);

        ////
        // Close column
        // The column is closed if the user set more than one column in question attribute
        // and if the max answer rows by column is reached.
        // If max answer rows by column is not reached while there is no more answer,
        // the column will remain opened, and it will be closed by 'other' answer row if set or at the end of the process
        if($iNbCols > 1 && $iRowCount == $iMaxRowsByColumn )
        {
            $last      = ($i == $anscount)?true:false; // If this loop count equal to the number of answers, then this answer is the last one.
            $sRows    .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/columns/column_footer', array('last'=>$last), true);
            $iRowCount = 0;
            $isOpen    = false;
        }
    }

    if (isset($other) && $other=='Y')
    {
        $iRowCount++; $i++;
        $sSeparator = getRadixPointData($thissurvey['surveyls_numberformat']);
        $sSeparator = $sSeparator['separator'];

        if ($aQuestionAttributes['other_numbers_only']==1)
        {
            $oth_checkconditionFunction = 'fixnum_checkconditions';
        }
        else
        {
            $oth_checkconditionFunction = 'checkconditions';
        }


        if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == '-oth-')
        {
            $checkedState = CHECKED;
        }
        else
        {
            $checkedState = '';
        }

        $myfname = $thisfieldname = $ia[1].'other';

        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$thisfieldname]))
        {
            $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$thisfieldname];
            if ($aQuestionAttributes['other_numbers_only']==1)
            {
                $dispVal = str_replace('.',$sSeparator,$dispVal);
            }
            $answer_other = ' value="'.htmlspecialchars($dispVal,ENT_QUOTES).'"';
        }
        else
        {
            $answer_other = ' value=""';
        }

        ////
        // Open Column
        // The column is opened if user set more than one column in question attribute
        // and if this is the first answer row (should never happen for 'other'),
        // or if the column has been closed and the row count reset before.
        if($iNbCols > 1 && $iRowCount == 1 )
        {
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/columns/column_header', array('iColumnWidth' => $iColumnWidth, 'first'=>false), true);
        }
        $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

        ////
        // Insert row
        // Display the answer row
        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/rows/answer_row_other', array(
                'name' => $ia[1],
                'answer_other'=>$answer_other,
                'myfname'=>$myfname,
                'sDisplayStyle' => $sDisplayStyle,
                'othertext'=>$othertext,
                'checkedState'=>$checkedState,
                'kpclass'=>$kpclass,
                'oth_checkconditionFunction'=>$oth_checkconditionFunction.'(this.value, this.name, this.type)',
                'checkconditionFunction'=>$checkconditionFunction,
        ), true);

        $inputnames[]=$thisfieldname;

        ////
        // Close column
        // The column is closed if the user set more than one column in question attribute
        // We can't be sure it's the last one because of 'no answer' item
        if($iNbCols > 1 && $iRowCount == $iMaxRowsByColumn )
        {
            $last = ($i == $anscount)?true:false; // If this loop count equal to the number of answers, then this answer is the last one.
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/columns/column_footer', array(), true);
            $iRowCount = 0;
            $isOpen = false;
        }

    }

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
    {
        $iRowCount++; $i++;

        if ((!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == '') || ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == ' ' ))
        {
            $check_ans = CHECKED; //Check the "no answer" radio button if there is no answer in session.
        }
        else
        {
            $check_ans = '';
        }

        if($iNbCols > 1 && $iRowCount == 1 )
        {
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/columns/column_header', array('iColumnWidth' => $iColumnWidth), true);
        }

        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/rows/answer_row_noanswer', array(
            'name'=>$ia[1],
            'check_ans'=>$check_ans,
            'checkconditionFunction'=>$checkconditionFunction,
        ), true);


        ////
        // Close column
        // The column is closed if the user set more than one column in question attribute
        // 'No answer' is always the last answer, so it's always closing the col and the bootstrap row containing the columns
        if($iNbCols > 1 )
        {
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/columns/column_footer', array('last'=>true), true);
            $iRowCount = 0;
            $isOpen = false;
        }
    }


    ////
    // Close column
    // The column is closed if the user set more than one column in question attribute
    // and if on column has been opened and not closed
    // That can happen only when no 'other' option is set, and the maximum answer rows has not been reached in the last question
    if($iNbCols > 1 && $isOpen )
    {
        $answer .= Yii::app()->getController()->renderPartial('/survey/questions/listradio/columns/column_footer', array('last'=>true), true);
        $iRowCount = 0;
    }

    //END OF ITEMS

    // ==> answer
    $answer = Yii::app()->getController()->renderPartial('/survey/questions/listradio/answer', array(
            'sTimer'=>$sTimer,
            'sRows' => $sRows,
            'name'  => $ia[1],
            'value' => $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],
    ), true);

    $inputnames[]=$ia[1];
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
// TMSW TODO - Can remove DB query by passing in answer list from EM
function do_listwithcomment($ia)
{
    //// Init variables

    // General variables
    global $dropdownthreshold;
    global $thissurvey;
    $dropdownthreshold      = Yii::app()->getConfig("dropdownthreshold");
    $kpclass                = testKeypad($thissurvey['nokeyboard']); // Virtual keyboard (probably obsolete today)
    $checkconditionFunction = "checkconditions";
    $iSurveyId              = Yii::app()->getConfig('surveyID'); // survey id
    $sSurveyLang            = $_SESSION['survey_'.$iSurveyId]['s_lang']; // survey language

    if (!isset($maxoptionsize))
    {
        $maxoptionsize=35;
    }


    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);                       // Question attribute variables
    $oQuestion           = Question::model()->findByPk(array('qid'=>$ia[0], 'language'=>$sSurveyLang));     // Getting question

    // Getting answers
    $ansresult    = $oQuestion->getOrderedAnswers($aQuestionAttributes['random_order'], $aQuestionAttributes['alphasort'] );
    $anscount     = count($ansresult);
    $hint_comment = gT('Please enter your comment here');

    if ($aQuestionAttributes['use_dropdown']!=1 && $anscount <= $dropdownthreshold)
    {

        $sRows = '';
        foreach ($ansresult as $ansrow)
        {
            $check_ans = '';

            if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == $ansrow['code'])
            {
                $check_ans = CHECKED;
            }

            $itemData = array(
                'name'                   => $ia[1],
                'id'                     => 'answer'.$ia[1].$ansrow['code'],
                'value'                  => $ansrow['code'],
                'check_ans'              => $check_ans,
                'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type);',
                'labeltext'              => $ansrow['answer'],
            );
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/list_with_comment/list/rows/answer_row', $itemData, true);
        }

        // ==> rows
        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
        {
            if ((!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == '') ||($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == ' ' ))
            {
                $check_ans = CHECKED;
            }
            elseif (($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] != ''))
            {
                $check_ans = '';
            }

            $itemData = array(
                'li_classes'=>' noanswer-item',
                'name'=>$ia[1],
                'id'=>'answer'.$ia[1],
                'value'=>'',
                'check_ans'=>$check_ans,
                'checkconditionFunction'=>$checkconditionFunction.'(this.value, this.name, this.type)',
                'labeltext'=>gT('No answer'),
            );

            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/list_with_comment/list/rows/answer_row', $itemData, true);
        }

        $fname2 = $ia[1].'comment';
        $tarows = ($anscount > 8)?$anscount/1.2:4;


        $answer = Yii::app()->getController()->renderPartial('/survey/questions/list_with_comment/list/answer', array(
            'sRows'             => $sRows,
            'id'                => 'answer'.$ia[1].'comment',
            'hint_comment'      => $hint_comment,
            'kpclass'           => $kpclass,
            'name'              => $ia[1].'comment',
            'tarows'            => floor($tarows),
            'has_comment_saved' => isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$fname2]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$fname2],
            'comment_saved'     => htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$fname2]),
            'java_name'         => 'java'.$ia[1],
            'java_id'           => 'java'.$ia[1],
            'java_value'        => $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]
        ), true);


        $inputnames[]=$ia[1];
        $inputnames[]=$ia[1].'comment';
    }
    else //Dropdown list
    {
        $sOptions= '';
        foreach ($ansresult as $ansrow)
        {
            $check_ans = '';
            if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == $ansrow['code'])
            {
                $check_ans = SELECTED;
            }

            $itemData = array(
                'value' => $ansrow['code'],
                'check_ans' => $check_ans,
                'option_text' => $ansrow['answer'],
            );
            $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_with_comment/dropdown/rows/option', $itemData, true);

            if (strlen($ansrow['answer']) > $maxoptionsize)
            {
                $maxoptionsize = strlen($ansrow['answer']);
            }
        }
        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1 && !is_null($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]))
        {
            $check_ans="";
            if (trim($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]) == '')
            {
                $check_ans = SELECTED;
            }
            $itemData = array(
                'classes' => ' noanswer-item ',
                'value' => '',
                'check_ans' => $check_ans,
                'option_text' => gT('No answer'),
            );
            $sOptions .= Yii::app()->getController()->renderPartial('/survey/questions/list_with_comment/dropdown/rows/option', $itemData, true);
        }
        $fname2 = $ia[1].'comment';

        if ($anscount > 8)
        {
            $tarows = $anscount/1.2;
        }
        else
        {
            $tarows = 4;
        }

        if ($tarows > 15)
        {
            $tarows=15;
        }
        $maxoptionsize=$maxoptionsize*0.72;

        if ($maxoptionsize < 33) {$maxoptionsize=33;}
        if ($maxoptionsize > 70) {$maxoptionsize=70;}


        $answer = Yii::app()->getController()->renderPartial('/survey/questions/list_with_comment/dropdown/answer', array(
            'sOptions'               => $sOptions,
            'name'                   => $ia[1],
            'id'                     => 'answer'.$ia[1],
            'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
            'show_noanswer'          => is_null($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]),
            'label_text'             => $hint_comment,
            'kpclass'                => $kpclass,
            'tarows'                 => $tarows,
            'maxoptionsize'          => $maxoptionsize,
            'has_comment_saved'      => isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$fname2]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$fname2],
            'comment_saved'          => htmlspecialchars( $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$fname2]),
            'value'                  => $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],
        ), true);

        $inputnames[]=$ia[1];
        $inputnames[]=$ia[1].'comment';
    }
    return array($answer, $inputnames);
}

function do_ranking($ia)
{
    global $thissurvey;
    $imageurl               = Yii::app()->getConfig("imageurl");
    $checkconditionFunction = "checkconditions";
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{answers}} WHERE qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and scale_id=0 ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{answers}} WHERE qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and scale_id=0 ORDER BY sortorder, answer";
    }

    $ansresult = Yii::app()->db->createCommand($ansquery)->query()->readAll();
    $anscount  = count($ansresult);

    if (trim($aQuestionAttributes["max_answers"])!='')
    {
        $max_answers = trim($aQuestionAttributes["max_answers"]);
    }
    else
    {
        $max_answers = $anscount;
    }
    // Get the max number of line needed
    if(ctype_digit($max_answers) && intval($max_answers)<$anscount)
    {
        $iMaxLine = $max_answers;
    }
    else
    {
        $iMaxLine = $anscount;
    }
    if (trim($aQuestionAttributes["min_answers"])!='')
    {
        $min_answers = trim($aQuestionAttributes["min_answers"]);
    } else
    {
        $min_answers = 0;
    }
    $answer = '';
    // First start by a ranking without javascript : just a list of select box
    // construction select box
    $answers= array();

    foreach ($ansresult as $ansrow)
    {
        $answers[] = $ansrow;
    }

    $inputnames = array();
    $sSelects = '';

    for ($i=1; $i<=$iMaxLine; $i++)
    {
        $myfname=$ia[1].$i;
        $labeltext = ($i==1)?gT('First choice'):sprintf(gT('Choice of rank %s'),$i);
        $itemDatas = array();

        if (!$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname])
        {
            $itemDatas[] = array(
                'value'      => '',
                'selected'   => 'SELECTED',
                'classes'    => '',
                'id'         => '',
                'optiontext' => gT('Please choose...'),
            );
        }

        foreach ($answers as $ansrow)
        {
            $thisvalue="";
            if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == $ansrow['code'])
            {
                $selected = SELECTED;
                $thisvalue=$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
            }
            else
            {
                $selected = '';
            }

            $itemDatas[] = array(
                'value' => $ansrow['code'],
                'selected'=>$selected,
                'classes'=>'',
                'optiontext'=>flattenText($ansrow['answer'])
            );

        }

        $sSelects .= Yii::app()->getController()->renderPartial(
            '/survey/questions/ranking/rows/answer_row',
            array(
                'myfname' => $myfname,
                'labeltext' => $labeltext,
                'options' => $itemDatas,
                'thisvalue' => $thisvalue
            ),
            true
        );

        $inputnames[]=$myfname;
    }

    App()->getClientScript()->registerPackage('jquery-actual'); // Needed to with jq1.9 ?
    Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."ranking.js");
    Yii::app()->getClientScript()->registerCssFile(Yii::app()->getConfig('publicstyleurl') . "ranking.css");

    if(trim($aQuestionAttributes['choice_title'][App()->language]) != '')
    {
        $choice_title=htmlspecialchars(trim($aQuestionAttributes['choice_title'][App()->language]), ENT_QUOTES);
    }
    else
    {
        $choice_title=gT("Your Choices",'js');
    }
    if(trim($aQuestionAttributes['rank_title'][App()->language]) != '')
    {
        $rank_title=htmlspecialchars(trim($aQuestionAttributes['rank_title'][App()->language]), ENT_QUOTES);
    }
    else
    {
        $rank_title=gT("Your Ranking",'js');
    }
    // hide_tip is managed by css with EM
    $rank_help = gT("Double-click or drag-and-drop items in the left list to move them to the right - your highest ranking item should be on the top right, moving through to your lowest ranking item.",'js');

    $answer .= Yii::app()->getController()->renderPartial('/survey/questions/ranking/answer', array(
                    'sSelects'          => $sSelects,
                    'thisvalue'         => $thisvalue,
                    'answers'           => $answers,
                    'myfname'           => $myfname,
                    'labeltext'         => $labeltext,
                    'rankId'            => $ia[0],
                    'rankingName'       => $ia[1],
                    'max_answers'       => $max_answers,
                    'min_answers'       => $min_answers,
                    'answers'           => $answers,
                    'choice_title'      => $choice_title,
                    'rank_title'        => $rank_title,
                    'rank_help'         => $rank_help,
                    'showpopups'        => $aQuestionAttributes["showpopups"],
                    'samechoiceheight'  => $aQuestionAttributes["samechoiceheight"],
                    'samelistheight'    => $aQuestionAttributes["samelistheight"],
            ), true);
    return array($answer, $inputnames);
}

function testKeypad($sUseKeyPad)
{
    if ($sUseKeyPad=='Y')
    {
        includeKeypad();
        $kpclass = "text-keypad";
    }
    else
    {
        $kpclass = "";
    }
    return $kpclass;
}

// ---------------------------------------------------------------
function do_multiplechoice($ia)
{
    //// Init variables

    // General variables
    global $thissurvey;
    $kpclass                = testKeypad($thissurvey['nokeyboard']);     // Virtual keyboard (probably obsolete today)
    $inputnames             = array();                                   // TODO : check if really used
    $checkconditionFunction = "checkconditions";                         // name of the function to check condition TODO : check is used more than once
    $iSurveyId              = Yii::app()->getConfig('surveyID');         // survey id
    $sSurveyLang            = $_SESSION['survey_'.$iSurveyId]['s_lang']; // survey language

    // Question attribute variables
    $aQuestionAttributes    = getQuestionAttributeValues($ia[0]);                                                                                                          // Question attributes
    $othertext              = (trim($aQuestionAttributes['other_replace_text'][$sSurveyLang])!='')?$aQuestionAttributes['other_replace_text'][$sSurveyLang]:gT('Other:');  // text for 'other'
    $iNbCols                = (trim($aQuestionAttributes['display_columns'])!='')?$aQuestionAttributes['display_columns']:1;                                               // number of columns

    if ($aQuestionAttributes['other_numbers_only']==1)
    {
        $sSeparator                 = getRadixPointData($thissurvey['surveyls_numberformat']);
        $sSeparator                 = $sSeparator['separator'];
        $oth_checkconditionFunction = "fixnum_checkconditions";
    }
    else
    {
        $oth_checkconditionFunction = "checkconditions";
    }

    //// Retrieving datas

    // Getting question
    $oQuestion = Question::model()->findByPk(array('qid'=>$ia[0], 'language'=>$sSurveyLang));
    $other     = $oQuestion->other;

    // Getting answers
    $ansresult = $oQuestion->getOrderedSubQuestions($aQuestionAttributes['random_order'], $aQuestionAttributes['exclude_all_others'] );
    $anscount  = count($ansresult);
    $anscount  = ($other == 'Y') ? $anscount+1 : $anscount; //COUNT OTHER AS AN ANSWER FOR MANDATORY CHECKING!

    //// Columns containing answer rows, set by user in question attribute
    /// TODO : move to a dedicated function

    // setting variables
    $iMaxRowsByColumn = 0;           // How many answer rows by column
    $iRowCount        = 0;
    $isOpen           = false;       // Is a column opened

    // TODO: check if still used
    if($iNbCols > 1)
    {
        // First we calculate the width of each column
        // Max number of column is 12 http://getbootstrap.com/css/#grid
        $iColumnWidth = round(12 / $iNbCols);
        $iColumnWidth = ($iColumnWidth >= 1 )?$iColumnWidth:1;
        $iColumnWidth = ($iColumnWidth <= 12)?$iColumnWidth:12;

        // Then, we calculate how many answer rows in each column
        $iMaxRowsByColumn = ceil($anscount / $iNbCols);
        $first = true; // The very first item will open a bootstrap row containing the columns
    }

    /// Generate answer rows
    $i = 0;

    $sRows = '';
    foreach ($ansresult as $ansrow)
    {
        $i++;                                       // general count of loop, to check if the item is the last one for column process. Never reset.
        $iRowCount++;                               // counter of number of row by column. Is reset to zero each time a column is full.
        $myfname     = $ia[1].$ansrow['title'];
        $extra_class ="";

        /* Check for array_filter */
        $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);
        $checkedState  = '';

        /* If the question has already been ticked, check the checkbox */
        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
        {
            if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'Y')
            {
                $checkedState = 'CHECKED';
            }
        }

        $sCheckconditionFunction = $checkconditionFunction.'(this.value, this.name, this.type)';
        $sValue                  = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))?$sValue = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]:'';
        $inputnames[]            = $myfname;

        ////
        // Open Column
        // The column is opened if user set more than one column in question attribute
        // and if this is the first answer row, or if the column has been closed and the row count reset before.
        if($iNbCols > 1 && $iRowCount == 1 )
        {
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/columns/column_header', array(
                'iColumnWidth' => $iColumnWidth,
                'first'        => $first), true);
            $isOpen  = true;  // If a column is not closed, it will be closed at the end of the process
            $first   = false; // The row containing the column has been opened at the first call.
        }

        ////
        // Insert row
        // Display the answer row
        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/rows/answer_row', array(
            'extra_class'             => $extra_class,
            'sDisplayStyle'           => $sDisplayStyle,
            'name'                    => $ia[1],  // field name
            'title'                   => $ansrow['title'],
            'question'                => $ansrow['question'],
            'ansrow'                  => $ansrow,
            'checkedState'            => $checkedState,
            'sCheckconditionFunction' => $sCheckconditionFunction,
            'myfname'                 => $myfname,
            'sValue'                  => $sValue,
        ), true);

        ////
        // Close column
        // The column is closed if the user set more than one column in question attribute
        // and if the max answer rows by column is reached.
        // If max answer rows by column is not reached while there is no more answer,
        // the column will remain opened, and it will be closed by 'other' answer row if set or at the end of the process
        if($iNbCols > 1 && $iRowCount == $iMaxRowsByColumn )
        {
            $last      = ($i == $anscount)?true:false; // If this loop count equal to the number of answers, then this answer is the last one.
            $sRows   .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/columns/column_footer', array('last'=>$last), true);
            $iRowCount = 0;
            $isOpen    = false;
        }
    }

    //==>  rows
    if ($other == 'Y')
    {
        $iRowCount++;
        $myfname = $ia[1].'other';

        $checkedState = '';
        // othercbox can be not display, because only input text goes to database
        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && trim($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname])!='')
        {
            $checkedState = 'CHECKED';
        }

        $sValue = '';
        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
        {
            $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
            if ($aQuestionAttributes['other_numbers_only']==1)
            {
                $dispVal = str_replace('.',$sSeparator,$dispVal);
            }
            $sValue .= htmlspecialchars($dispVal,ENT_QUOTES);
        }

        // TODO : check if $sValueHidden === $sValue
        $sValueHidden ='';
        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
        {
            $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
            if ($aQuestionAttributes['other_numbers_only']==1)
            {
                $dispVal = str_replace('.',$sSeparator,$dispVal);
            }
            $sValueHidden = htmlspecialchars($dispVal,ENT_QUOTES);;
        }

        $inputnames[]=$myfname;
        ++$anscount;

        ////
        // Open Column
        // The column is opened if user set more than one column in question attribute
        // and if this is the first answer row (should never happen for 'other'),
        // or if the column has been closed and the row count reset before.
        if($iNbCols > 1 && $iRowCount == 1 )
        {
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/columns/column_header', array('iColumnWidth' => $iColumnWidth, 'first'=>false), true);
        }

        ////
        // Insert row
        // Display the answer row
        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/rows/answer_row_other', array(
            'myfname'                    => $myfname,
            'sDisplayStyle'              => $sDisplayStyle,
            'othertext'                  => $othertext,
            'checkedState'               => $checkedState,
            'kpclass'                    => $kpclass,
            'sValue'                     => $sValue,
            'oth_checkconditionFunction' => $oth_checkconditionFunction,
            'checkconditionFunction'     => $checkconditionFunction,
            'sValueHidden'               => $sValueHidden,
        ), true);

        ////
        // Close column
        // The column is closed if the user set more than one column in question attribute
        // Other is always the last answer, so it's always closing the col and the bootstrap row containing the columns
        if($iNbCols > 1 )
        {
            $sRows    .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/columns/column_footer', array('last'=>true), true);
            $iRowCount = 0;
            $isOpen    = false;
        }
    }

    ////
    // Close column
    // The column is closed if the user set more than one column in question attribute
    // and if on column has been opened and not closed
    // That can happen only when no 'other' option is set, and the maximum answer rows has not been reached in the last question
    if($iNbCols > 1 && $isOpen )
    {
        $sRows   .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/columns/column_footer', array('last'=>true), true);
        $iRowCount = 0;
    }

    // ==> answer
    $answer = Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice/answer', array(
                'sRows'    => $sRows,
                'name'     => $ia[1],
                'anscount' => $anscount,
            ), true);

    return array($answer, $inputnames);
}

function do_multiplechoice_withcomments($ia)
{
    global $thissurvey;
    $inputnames = array();
    $kpclass    = testKeypad($thissurvey['nokeyboard']);                                                            // Virtual keyboard (probably obsolete today)
    $inputnames = array();
    $qaquery    = "SELECT qid,attribute FROM {{question_attributes}} WHERE value LIKE '".strtolower($ia[2])."'";
    $qaresult   = Yii::app()->db->createCommand($qaquery)->query();                                                 //Checked

    foreach($qaresult->readAll() as $qarow)
    {
        $qquery  = "SELECT qid FROM {{questions}} WHERE sid=".$thissurvey['sid']." AND qid=".$qarow['qid'];
        $qresult = Yii::app()->db->createCommand($qquery)->query();                                                 //Checked
    }

    $checkconditionFunction = "checkconditions";
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if ($aQuestionAttributes['other_numbers_only']==1)
    {
        $sSeparator                 = getRadixPointData($thissurvey['surveyls_numberformat']);
        $sSeparator                 = $sSeparator['separator'];
        $oth_checkconditionFunction = "fixnum_checkconditions";
    }
    else
    {
        $oth_checkconditionFunction = "checkconditions";
    }

    if (trim($aQuestionAttributes['other_replace_text'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $othertext = $aQuestionAttributes['other_replace_text'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
    }
    else
    {
        $othertext=gT('Other:');
    }

    $qquery = "SELECT other FROM {{questions}} WHERE qid=".$ia[0]." AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and parent_qid=0";
    $other  = Yii::app()->db->createCommand($qquery)->queryScalar(); //Checked
    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0]  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0]  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
    }

    $ansresult = Yii::app()->db->createCommand($ansquery)->query();  //Checked
    $anscount  = count($ansresult)*2;
    $answer    = "";

    $fn = 1;
    if (!isset($other)){
        $other = 'N';
    }
    if($other == 'Y')
    {
        $label_width = 25;
    }
    else
    {
        $label_width = 0;
    }

    // Size of elements depends on longest text item
    $toIterate = $ansresult->readAll();
    $longest_question = 0;
    foreach ( $toIterate as $ansrow)
    {
        $current_length = round((strlen($ansrow['question'])/10)+1);
        $longest_question = ( $longest_question > $current_length)?$longest_question:$current_length;
    }

    $nbCol = $longest_question;
    $sRows = "";
    foreach ($toIterate as $ansrow)
    {
        $myfname = $ia[1].$ansrow['title'];
        $trbc='';

        /* Check for array_filter */
        $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

        if($label_width < strlen(trim(strip_tags($ansrow['question']))))
        {
            $label_width = strlen(trim(strip_tags($ansrow['question'])));
        }

        $myfname2 = $myfname."comment";

        /* If the question has already been ticked, check the checkbox */
        $checked = '';
        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
        {
            if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'Y')
            {
                $checked = CHECKED;
            }
        }

        $javavalue = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))?$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]:'';

        $fn++;
        $fn++;
        $inputnames[]=$myfname;
        $inputnames[]=$myfname2;

        $inputCOmmentValue = htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2],ENT_QUOTES);
        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice_with_comments/rows/answer_row', array(
            'sDisplayStyle'                 => $sDisplayStyle,
            'kpclass'                       => $kpclass,
            'title'                         => '',
            'liclasses'                     => 'responsive-content question-item answer-item checkbox-text-item',
            'name'                          => $myfname,
            'id'                            => 'answer'.$myfname,
            'value'                         => 'Y', // TODO : check if it should be the same than javavalue
            'classes'                       => '',
            'checkconditionFunction'        => $checkconditionFunction.'(this.value, this.name, this.type)',
            'checkconditionFunctionComment' => $checkconditionFunction.'(this.value, this.name, this.type)',
            'labeltext'                     => $ansrow['question'],
            'javainput'                     => true,
            'javaname'                      => 'java'.$myfname,
            'javavalue'                     => $javavalue,
            'checked'                       => $checked,
            'inputCommentId'                => 'answer'.$myfname2,
            'commentLabelText'              => gT('Make a comment on your choice here:'),
            'inputCommentName'              => $myfname2,
            'inputCOmmentValue'             => $inputCOmmentValue,
        ), true);

    }
    if ($other == 'Y')
    {
        $myfname = $ia[1].'other';
        $myfname2 = $myfname.'comment';
        $anscount = $anscount + 2;
        // SPAN LABEL OPTION //////////////////////////
        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname])
        {
            $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
            if ($aQuestionAttributes['other_numbers_only']==1)
            {
                $dispVal = str_replace('.',$sSeparator,$dispVal);
            }
            $value = htmlspecialchars($dispVal,ENT_QUOTES);
        }
        $fn++;

        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2]))
        {
            $inputCOmmentValue = htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2],ENT_QUOTES);
        }

        // TODO: $value is not defined for some execution paths.
        if (!isset($value))
        {
            $value = '';
        }

        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice_with_comments/rows/answer_row', array(
            'liclasses'                     => 'other question-item answer-item checkbox-text-item other-item',
            'liid'                          => 'javatbd'.$myfname,
            'kpclass'                       => $kpclass,
            'title'                         => gT('Other'),
            'sDisplayStyle'                 => $sDisplayStyle,
            'name'                          => $myfname,
            'id'                            => 'answer'.$myfname,
            'value'                         => $value, // TODO : check if it should be the same than javavalue
            'classes'                       => '',
            'checkconditionFunction'        => $oth_checkconditionFunction.'(this.value, this.name, this.type)',
            'checkconditionFunctionComment' => $checkconditionFunction.'(this.value, this.name, this.type)',
            'labeltext'                     => $othertext,
            'inputCommentId'                => 'answer'.$myfname2,
            'commentLabelText'              => gT('Make a comment on your choice here:'),
            'inputCommentName'              => $myfname2,
            'inputCOmmentValue'             => $inputCOmmentValue,
            'checked'                       => $checked,
            'javainput'                     => false,
            'javaname'                      => '',
            'javavalue'                     => '',
        ), true);
        $inputnames[]=$myfname;
        $inputnames[]=$myfname2;
    }

    $answer = Yii::app()->getController()->renderPartial('/survey/questions/multiplechoice_with_comments/answer', array(
        'sRows' => $sRows,
        'name'=>'MULTI'.$ia[1],
        'value'=> $anscount
    ), true);


    if($aQuestionAttributes['commented_checkbox']!="allways" && $aQuestionAttributes['commented_checkbox_auto'])
    {
        Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."multiplechoice_withcomments.js");
        $answer .= "<script type='text/javascript'>\n"
        . "  /*<![CDATA[*/\n"
        ." doMultipleChoiceWithComments({$ia[0]},'{$aQuestionAttributes["commented_checkbox"]}');\n"
        ." /*]]>*/\n"
        ."</script>\n";
    }

    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_file_upload($ia)
{
    global $thissurvey;
    $checkconditionFunction = "checkconditions";
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    // Fetch question attributes
    $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['fieldname'] = $ia[1];
    $scriptloc = Yii::app()->getController()->createUrl('uploader/index');
    $bPreview=Yii::app()->request->getParam('action')=="previewgroup" || Yii::app()->request->getParam('action')=="previewquestion" || $thissurvey['active'] != "Y";

    if ($bPreview)
    {
        $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['preview'] = 1 ;
        $questgrppreview = 1;   // Preview is launched from Question or group level
    }
    elseif ($thissurvey['active'] != "Y")
    {
        $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['preview'] = 1;
        $questgrppreview = 0;
    }
    else
    {
        $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['preview'] = 0;
        $questgrppreview = 0;
    }

    $answer = "<script type='text/javascript'>
        function upload_$ia[1]() {
            var uploadurl = '{$scriptloc}?sid=".Yii::app()->getConfig('surveyID')."&fieldname={$ia[1]}&qid={$ia[0]}';
            uploadurl += '&preview={$questgrppreview}&show_title={$aQuestionAttributes['show_title']}';
            uploadurl += '&show_comment={$aQuestionAttributes['show_comment']}';
            uploadurl += '&minfiles=' + LEMval('{$aQuestionAttributes['min_num_of_files']}');
            uploadurl += '&maxfiles=' + LEMval('{$aQuestionAttributes['max_num_of_files']}');
            $('#upload_$ia[1]').attr('href',uploadurl);
        }
        var uploadLang = {
             title: '" . gT('Upload your files','js') . "',
             returnTxt: '" . gT('Return to survey','js') . "',
             headTitle: '" . gT('Title','js') . "',
             headComment: '" . gT('Comment','js') . "',
             headFileName: '" . gT('File name','js') . "',
             deleteFile : '".gT('Delete')."',
             editFile : '".gT('Edit')."'
            };
        var imageurl =  '".Yii::app()->getConfig('imageurl')."';
        var uploadurl =  '".$scriptloc."';
    </script>\n";
    Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."modaldialog.js");
    Yii::app()->getClientScript()->registerCssFile(Yii::app()->getConfig('publicstyleurl') . "uploader-files.css");
    // Modal dialog
    //$answer .= $uploadbutton;

    $filecountvalue = '0';
    if (array_key_exists($ia[1]."_filecount", $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]))
    {
        $tempval = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]."_filecount"];
        if (is_numeric($tempval))
        {
            $filecountvalue = $tempval;
        }
    }
    $value = htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],ENT_QUOTES,'utf-8');
    $fileuploadDatas = array(
        'fileid' => $ia[1],
        'value' => $value,
        'filecountvalue'=>$filecountvalue,
    );
    $answer .= Yii::app()->getController()->renderPartial('/survey/questions/file_upload/answer', $fileuploadDatas, true);

    $answer .= '<script type="text/javascript">
    var surveyid = '.Yii::app()->getConfig('surveyID').';
    $(document).ready(function(){
    var fieldname = "'.$ia[1].'";
    var filecount = $("#"+fieldname+"_filecount").val();
    var json = $("#"+fieldname).val();
    var show_title = "'.$aQuestionAttributes["show_title"].'";
    var show_comment = "'.$aQuestionAttributes["show_comment"].'";
    displayUploadedFiles(json, filecount, fieldname, show_title, show_comment);
    });
    </script>';

    $answer .= '<script type="text/javascript">
    $(".basic_'.$ia[1].'").change(function() {
    var i;
    var jsonstring = "[";

    for (i = 1, filecount = 0; i <= LEMval("'.$aQuestionAttributes['max_num_of_files'].'"); i++)
    {
    if ($("#'.$ia[1].'_"+i).val() == "")
    continue;

    filecount++;
    if (i != 1)
    jsonstring += ", ";

    if ($("#answer'.$ia[1].'_"+i).val() != "")
    jsonstring += "{ ';

    if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['show_title']))
        $answer .= '\"title\":\""+$("#'.$ia[1].'_title_"+i).val()+"\",';
    else
        $answer .= '\"title\":\"\",';

    if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['show_comment']))
        $answer .= '\"comment\":\""+$("#'.$ia[1].'_comment_"+i).val()+"\",';
    else
        $answer .= '\"comment\":\"\",';

    $answer .= '\"size\":\"\",\"name\":\"\",\"ext\":\"\"}";
    }
    jsonstring += "]";

    $("#'.$ia[1].'").val(jsonstring);
    $("#'.$ia[1].'_filecount").val(filecount);
    });
    </script>';

    $uploadurl  = $scriptloc . "?sid=" . Yii::app()->getConfig('surveyID') . "&fieldname=" . $ia[1] . "&qid=" . $ia[0];
    $uploadurl .= "&preview=" . $questgrppreview . "&show_title=" . $aQuestionAttributes['show_title'];
    $uploadurl .= "&show_comment=" . $aQuestionAttributes['show_comment'];
    $uploadurl .= "&minfiles=" . $aQuestionAttributes['min_num_of_files'];  // TODO: Regression here? Should use LEMval(minfiles) like above
    $uploadurl .= "&maxfiles=" . $aQuestionAttributes['max_num_of_files'];  // Same here.

    $answer .= '
        <!-- Trigger the modal with a button -->
        <!-- <button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target="#myModal">Open Modal</button>-->

        <!-- Modal -->
        <div id="file-upload-modal-' . $ia[1] . '" class="modal fade" role="dialog">
            <div class="modal-dialog">

                <!-- Modal content-->
                <div class="modal-content" style="vertical-align: middle;">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">' . ngT("Upload file|Upload files", $aQuestionAttributes['max_num_of_files']) . '</h4>
                    </div>
                    <div class="modal-body file-upload-modal-body">
                        <iframe id="uploader" name="uploader" class="externalSite" src="' . $uploadurl . '"></iframe>
                    </div>
                    <div class="modal-footer file-upload-modal-footer">
                        <button type="button" class="btn btn-success" data-dismiss="modal">' . gT("Save changes") . '</button>
                    </div>
                </div>

            </div>
        </div>
    ';

    $inputnames[] = $ia[1];
    $inputnames[] = $ia[1]."_filecount";
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
// TMSW TODO - Can remove DB query by passing in answer list from EM
function do_multipleshorttext($ia)
{
    global $thissurvey;
    $extraclass          = "";
    $answer              = '';
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if ($aQuestionAttributes['numbers_only']==1)
    {
        $sSeparator             = getRadixPointData($thissurvey['surveyls_numberformat']);
        $sSeparator             = $sSeparator['separator'];
        $extraclass            .= " numberonly";
        $checkconditionFunction = "fixnum_checkconditions";
    }
    else
    {
        $checkconditionFunction = "checkconditions";
    }

    if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
    {
        // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maximum_chars = intval(trim($aQuestionAttributes['maximum_chars']));
        $maxlength     = "maxlength='{$maximum_chars}' ";
        $extraclass   .= " maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength = "";
    }

    $tiwidth              = (trim($aQuestionAttributes['text_input_width'])!='')?$aQuestionAttributes['text_input_width']:'';
    $sInputContainerWidth = (trim($aQuestionAttributes['text_input_columns'])!='')?$aQuestionAttributes['text_input_columns']:'6';
    $sLabelWidth          = (trim($aQuestionAttributes['label_input_columns'])!='')?$aQuestionAttributes['label_input_columns']:'6';

    if (trim($aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $prefix      = $aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withprefix";
    }
    else
    {
        $prefix = '';
    }

    if (trim($aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $suffix      = $aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withsuffix";
    }
    else
    {
        $suffix = '';
    }
    $kpclass = testKeypad($thissurvey['nokeyboard']); // Virtual keyboard (probably obsolete today)

    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0]  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0]  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
    }

    $ansresult     = dbExecuteAssoc($ansquery);    //Checked
    $aSubquestions = $ansresult->readAll();
    $anscount      = count($aSubquestions)*2;
    $fn            = 1;
    $answer_main   = '';
    $label_width   = 0;
    $sRows         = '';

    if ($anscount!=0)
    {
        // Display TextArea
        if (trim($aQuestionAttributes['display_rows'])!='')
        {
            //question attribute "display_rows" is set -> we need a textarea to be able to show several rows
            $drows=$aQuestionAttributes['display_rows'];

            foreach ($aSubquestions as $ansrow)
            {
                $myfname = $ia[1].$ansrow['title'];
                $ansrow['question'] = ($ansrow['question'] == "")?"&nbsp;":$ansrow['question'];

                /* Check for array_filter */
                $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);
                $dispVal ='';

                if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
                {
                    $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
                    if ($aQuestionAttributes['numbers_only']==1)
                    {
                        $dispVal = str_replace('.',$sSeparator,$dispVal);
                    }
                    $dispVal .= htmlspecialchars($dispVal);
                }

                $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multipleshorttext/rows/answer_row_textarea', array(
                    'alert'                  => false,
                    'tiwidth'                => $tiwidth,
                    'sInputContainerWidth'   => $sInputContainerWidth,
                    'sLabelWidth'            => $sLabelWidth,
                    'maxlength'              => '',
                    'extraclass'             => $extraclass,
                    'sDisplayStyle'          => $sDisplayStyle,
                    'prefix'                 => $prefix,
                    'myfname'                => $myfname,
                    'labelText'              => $ansrow['question'],
                    'prefix'                 => $prefix,
                    'kpclass'                => $kpclass,
                    'rows'                   => $drows,
                    'maxlength'              => $maxlength,
                    'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
                    'dispVal'                => $dispVal,
                    'suffix'                 => $suffix,
                ), true);

                $fn++;
                $inputnames[]=$myfname;
            }

        }
        // Diplay input text
        else
        {
            $alert = false;
            foreach ($aSubquestions as $ansrow)
            {
                $myfname = $ia[1].$ansrow['title'];
                $ansrow['question'] = ($ansrow['question'] == "")?"&nbsp;":$ansrow['question'];

                // color code missing mandatory questions red
                if ($ia[6]=='Y' &&  $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] === '')
                {
                    $alert = true;
                }

                $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);
                $dispVal       = '';

                if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
                {
                    $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
                    if ($aQuestionAttributes['numbers_only']==1)
                    {
                        $dispVal = str_replace('.',$sSeparator,$dispVal);
                    }
                    $dispVal = htmlspecialchars($dispVal,ENT_QUOTES,'UTF-8');
                }

                $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multipleshorttext/rows/answer_row_inputtext', array(
                    'alert'                  => $alert,
                    'labelname'              => 'answer'.$myfname,
                    'maxlength'              => $maxlength,
                    'tiwidth'                => $tiwidth,
                    'sInputContainerWidth'   => $sInputContainerWidth,
                    'sLabelWidth'            => $sLabelWidth,
                    'extraclass'             => $extraclass,
                    'sDisplayStyle'          => $sDisplayStyle,
                    'prefix'                 => $prefix,
                    'myfname'                => $myfname,
                    'question'               => $ansrow['question'],
                    'prefix'                 => $prefix,
                    'kpclass'                => $kpclass,
                    'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
                    'dispVal'                => $dispVal,
                    'suffix'                 => $suffix,
                ), true);
                $fn++;
                $inputnames[]=$myfname;
            }

        }

        $answer = Yii::app()->getController()->renderPartial('/survey/questions/multipleshorttext/answer', array(
                    'sRows' => $sRows,
                  ), true);

    }
    else
    {
        $inputnames   = array();
        $answer       = Yii::app()->getController()->renderPartial('/survey/questions/multipleshorttext/empty', array(), true);
    }

    return array($answer, $inputnames);
}

// -----------------------------------------------------------------
// @todo: Can remove DB query by passing in answer list from EM
function do_multiplenumeric($ia)
{
    global $thissurvey;
    $extraclass             = "";
    $checkconditionFunction = "fixnum_checkconditions";
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $answer                 = '';
    $sSeparator             = getRadixPointData($thissurvey['surveyls_numberformat']);
    $sSeparator             = $sSeparator['separator'];
    $extraclass            .= " numberonly";                                                //Must turn on the "numbers only javascript"

    if ($aQuestionAttributes['thousands_separator'] == 1)
    {
        App()->clientScript->registerPackage('jquery-price-format');
        App()->clientScript->registerScriptFile(Yii::app()->getConfig('generalscripts').'numerical_input.js');
        $extraclass .= " thousandsseparator";
    }

    if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
    {
        $maximum_chars = intval(trim($aQuestionAttributes['maximum_chars'])); // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maxlength     = "maxlength='{$maximum_chars}' ";
        $extraclass   .= " maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength = " maxlength='25' ";
    }

    if (trim($aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $prefix      = $aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withprefix";
    }
    else
    {
        $prefix = '';
    }

    if (trim($aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $suffix      = $aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withsuffix";
    }
    else
    {
        $suffix = '';
    }

    $kpclass            = testKeypad($thissurvey['nokeyboard']); // Virtual keyboard (probably obsolete today)
    $numbersonly_slider = ''; // DEPRECATED

    if (trim($aQuestionAttributes['text_input_width'])!='')
    {
        $tiwidth     = $aQuestionAttributes['text_input_width'];
        $col         = ($aQuestionAttributes['text_input_width']<=12)?$aQuestionAttributes['text_input_width']:12;
        $extraclass .= " col-sm-".trim($col);
    }
    else
    {
        $tiwidth = 10;
    }

    $prefixclass = "numeric";

    if ($aQuestionAttributes['slider_layout']==1)
    {
        $prefixclass          = "slider";
        $slider_layout        = true;
        $extraclass          .= " withslider";
        $slider_step          = trim(LimeExpressionManager::ProcessString("{{$aQuestionAttributes['slider_accuracy']}}",$ia[0],array(),false,1,1,false,false,true));
        $slider_step          = (is_numeric($slider_step))?$slider_step:1;
        $slider_min           = trim(LimeExpressionManager::ProcessString("{{$aQuestionAttributes['slider_min']}}",$ia[0],array(),false,1,1,false,false,true));
        $slider_mintext       = $slider_min =  (is_numeric($slider_min))?$slider_min:0;
        $slider_max           = trim(LimeExpressionManager::ProcessString("{{$aQuestionAttributes['slider_max']}}",$ia[0],array(),false,1,1,false,false,true));
        $slider_maxtext       = $slider_max =  (is_numeric($slider_max))?$slider_max:100;
        $slider_default       = trim(LimeExpressionManager::ProcessString("{{$aQuestionAttributes['slider_default']}}",$ia[0],array(),false,1,1,false,false,true));
        $slider_default       = (is_numeric($slider_default))?$slider_default:"";
        $slider_orientation   = (trim($aQuestionAttributes['slider_orientation'])==0)?'horizontal':'vertical';
        $slider_custom_handle = (trim($aQuestionAttributes['slider_custom_handle']));

        switch(trim($aQuestionAttributes['slider_handle']))
        {
            case 0:
                $slider_handle = 'round';
                break;

            case 1:
                $slider_handle = 'square';
                break;

            case 2:
                $slider_handle = 'triangle';
                break;

            case 3:
                $slider_handle = 'custom';
                break;
        }

        if ($slider_default == '' && $aQuestionAttributes['slider_middlestart']==1)
        {
            $slider_middlestart = intval(($slider_max + $slider_min)/2);
        }
        else
        {
            $slider_middlestart = '';
        }

        $slider_separator= (trim($aQuestionAttributes['slider_separator'])!='')?$aQuestionAttributes['slider_separator']:"";
        $slider_reset=($aQuestionAttributes['slider_reset'])?1:0;

    }
    else
    {
        $slider_layout  = false;
        $slider_step    = '';
        $slider_min     = '';
        $slider_mintext = '';
        $slider_max     = '';
        $slider_maxtext = '';
        $slider_default = '';
        $slider_orientation= '';
        $slider_handle = '';
        $slider_custom_handle = '';
        $slider_separator = '';
        $slider_reset = 0;
        $slider_startvalue = '';
        $slider_displaycallout = '';
    }
    $hidetip=$aQuestionAttributes['hide_tip'];

    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0]  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0]  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
    }

    $ansresult     = dbExecuteAssoc($ansquery);    //Checked
    $aSubquestions = $ansresult->readAll();
    $anscount      = count($aSubquestions)*2;
    $fn            = 1;
    $sRows         = "";

    $answer_main = '';

    if ($anscount==0)
    {
        $inputnames =array();
        $noanswer   = true;
        $answer    = Yii::app()->getController()->renderPartial('/survey/questions/multiplenumeric/empty', array(), true);
    }
    else
    {
        foreach($aSubquestions as $ansrow)
        {
            $labelText = $ansrow['question'];
            $myfname   = $ia[1].$ansrow['title'];

            if ($ansrow['question'] == "")
            {
                $ansrow['question'] = "&nbsp;";
            }

            if ($slider_layout === false || $slider_separator == '')
            {
                $theanswer = $ansrow['question'];
                $sliders   = false;
            }
            else
            {
                $aAnswer     = explode($slider_separator,$ansrow['question']);
                $theanswer   = (isset($aAnswer[0]))?$aAnswer[0]:"";
                $labelText   = $theanswer;
                $sliderleft  = (isset($aAnswer[1]))?$aAnswer[1]:"";
                $sliderright = (isset($aAnswer[2]))?$aAnswer[2]:"";
                $sliders     = true;
            }

            $aAnswer     = (isset($aAnswer))?$aAnswer:'';
            $sliderleft  = (isset($sliderleft))?$sliderleft:"";
            $sliderright = (isset($sliderright))?$sliderright:"";

            // color code missing mandatory questions red
            $alert='';
            if ($ia[6]=='Y' && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] === '')
            {
                $alert = true;
            }

            //list($htmltbody2, $hiddenfield)=return_array_filter_strings($ia, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, '', $myfname, "div","form-group question-item answer-item text-item numeric-item".$extraclass);
            $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

            // TODO : check why it's done here a second time
            $sSeparator = getRadixPointData($thissurvey['surveyls_numberformat']);
            $sSeparator = $sSeparator['separator'];


            // The value of the slider depends on many possible different parameters, by order of priority :
            // 1. The value stored in the session
            // 2. Else the default Answer   (set by EM and stored in session, so same case than 1)
            // 3. Else the init value
            // 4. Else the middle start
            // 5. If no value at all, or if middle start, the "user no action" is recorded as null in the database

            // For bootstrap slider, the value can't be NULL so we set it by default to the slider minimum value.
            // The old behaviour of "null" value (corresponding to user no action) is implemented via $slider_user_no_action
            // It could be used to show a temporary "No Answer" checkbox (hidden when user touch the slider)

            // Most of this javascript is here to handle the fact that bootstrapSlider need numerical value in the input
            // It can't accept "NULL" nor anyother thousand separator than "." (else it become a string)
            // See : https://github.com/LimeSurvey/LimeSurvey/blob/master/scripts/bootstrap-slider.js#l1453-l1461
            // If the bootstrapSlider were updated, most of this javascript would not be necessary.

            $sValue                = $slider_min;
            $slider_user_no_action =1;

            // value stored in _SESSION
            if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
            {
                $sValue                = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
                $slider_user_no_action = 0;
            }
            elseif( $slider_default != "" )
            {
                $sValue                = $slider_default;
                $slider_user_no_action = 0;
            }
            elseif( isset($slider_middlestart) && $slider_middlestart!='')
            {
                $sValue = $slider_middlestart;
            }

            $sUnformatedValue = $sValue;

            if(strpos($sValue,"."))
            {
                $sValue = rtrim(rtrim($sValue,"0"),".");
                $sValue = str_replace('.',$sSeparator,$sValue);
            }

            if(!$sliders)
            {
            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplenumeric/rows/input/answer_row', array(
                'qid'                    => $ia[0],
                'extraclass'             => $extraclass,
                'sDisplayStyle'          => $sDisplayStyle,
                'kpclass'                => $kpclass,
                'alert'                  => $alert,
                'theanswer'              => $theanswer,
                'labelname'              => 'answer'.$myfname,
                'prefixclass'            => $prefixclass,
                'prefix'                 => $prefix,
                'suffix'                 => $suffix,
                'tiwidth'                => $tiwidth,
                'myfname'                => $myfname,
                'dispVal'                => $sValue,
                'maxlength'              => $maxlength,
                'labelText'              => $labelText,
                'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
            ), true);
            }
            else
            {
                $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/multiplenumeric/rows/sliders/answer_row', array(
                    'qid'                    => $ia[0],
                    'extraclass'             => $extraclass,
                    'sDisplayStyle'          => $sDisplayStyle,
                    'kpclass'                => $kpclass,
                    'alert'                  => $alert,
                    'theanswer'              => $theanswer,
                    'labelname'              => 'answer'.$myfname,
                    'prefixclass'            => $prefixclass,
                    'sliders'                => $sliders,
                    'sliderleft'             => $sliderleft,
                    'sliderright'            => $sliderright,
                    'prefix'                 => $prefix,
                    'suffix'                 => $suffix,
                    'tiwidth'                => $tiwidth,
                    'myfname'                => $myfname,
                    'dispVal'                => $sValue,
                    'maxlength'              => $maxlength,
                    'labelText'              => $labelText,
                    'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
                    'slider_orientation'     => $slider_orientation,
                    'slider_step'            => $slider_step    ,
                    'slider_min'             => $slider_min     ,
                    'slider_mintext'         => $slider_mintext ,
                    'slider_max'             => $slider_max     ,
                    'slider_maxtext'         => $slider_maxtext ,
                    'slider_default'         => $slider_default ,
                    'slider_handle'          => $slider_handle,
                    'slider_reset'           => $slider_reset,
                    'slider_custom_handle'   => $slider_custom_handle,
                    'slider_user_no_action'  => $slider_user_no_action,
                    'slider_showminmax'      => $aQuestionAttributes['slider_showminmax'],
                    'sSeparator'             => $sSeparator,
                    'sUnformatedValue'       => $sUnformatedValue,
                ), true);
            }
            $fn++;
            $inputnames[]=$myfname;
        }
        $displaytotal     = false;
        $equals_num_value = false;

        if (trim($aQuestionAttributes['equals_num_value']) != ''
        || trim($aQuestionAttributes['min_num_value']) != ''
        || trim($aQuestionAttributes['max_num_value']) != ''
        )
        {
            $qinfo = LimeExpressionManager::GetQuestionStatus($ia[0]);

            if (trim($aQuestionAttributes['equals_num_value']) != '')
            {
                $equals_num_value = true;
            }

            $displaytotal = true;
        }


        $answer      = Yii::app()->getController()->renderPartial('/survey/questions/multiplenumeric/answer', array(
                        'sRows'            => $sRows,
                        'prefixclass'      => $prefixclass,
                        'equals_num_value' => $equals_num_value,
                        'id'               => $ia[0],
                        'prefix'           => $prefix,
                        'suffix'           => $suffix,
                        'sumRemainingEqn'  => (isset($qinfo))?$qinfo['sumRemainingEqn']:'',
                        'displaytotal'     => $displaytotal,
                        'sumEqn'           => (isset($qinfo))?$qinfo['sumEqn']:'',
                       ), true);

    }

    if($aQuestionAttributes['slider_layout']==1)
    {
        Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."bootstrap-slider.js");
    }

    $sSeparator = getRadixPointData($thissurvey['surveyls_numberformat']);
    $sSeparator = $sSeparator['separator'];

    return array($answer, $inputnames);
}





// ---------------------------------------------------------------
function do_numerical($ia)
{
    global $thissurvey;
    $extraclass             = "";
    $answertypeclass        = "numeric";
    $checkconditionFunction = "fixnum_checkconditions";
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if (trim($aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $prefix      = $aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withprefix";
    }
    else
    {
        $prefix = '';
    }

    if ($aQuestionAttributes['thousands_separator'] == 1)
    {
        App()->clientScript->registerPackage('jquery-price-format');
        App()->clientScript->registerScriptFile(Yii::app()->getConfig('generalscripts').'numerical_input.js');
        $extraclass .= " thousandsseparator";
    }

    if (trim($aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $suffix      = $aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withsuffix";
    }
    else
    {
        $suffix = '';
    }
    if (intval(trim($aQuestionAttributes['maximum_chars']))>0 && intval(trim($aQuestionAttributes['maximum_chars']))<20)
    {
        // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maximum_chars= intval(trim($aQuestionAttributes['maximum_chars']));
        $maxlength= " maxlength='{$maximum_chars}' ";
        $extraclass .=" maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength= " maxlength='20' ";
    }
    if (trim($aQuestionAttributes['text_input_width'])!='')
    {
        $tiwidth     = $aQuestionAttributes['text_input_width'];
        $col         = ($aQuestionAttributes['text_input_width']<=12)?$aQuestionAttributes['text_input_width']:12;
        $extraclass .= " col-sm-".trim($col);
    }
    else
    {
        $tiwidth = 10;
    }

    if (trim($aQuestionAttributes['num_value_int_only'])==1)
    {
        $acomma           = "";
        $extraclass      .= " integeronly";
        $answertypeclass .= " integeronly";
        $integeronly      = 1;
    }
    else
    {
        $acomma      = getRadixPointData($thissurvey['surveyls_numberformat']);
        $acomma      = $acomma['separator'];
        $integeronly = 0;
    }

    $fValue     = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]];
    $sSeparator = getRadixPointData($thissurvey['surveyls_numberformat']);
    $sSeparator = $sSeparator['separator'];

    // Fix the display value : Value is stored as decimal in SQL then return dot and 0 after dot. Seems only for numerical question type
    if(strpos($fValue,"."))
    {
        $fValue = rtrim(rtrim($fValue,"0"),".");
    }
    $fValue = str_replace('.',$sSeparator,$fValue);

    if ($thissurvey['nokeyboard']=='Y')
    {
        includeKeypad();
        $extraclass      .= " inputkeypad";
        $answertypeclass .= " num-keypad";
    }
    else
    {
        $kpclass = "";
    }

    $answer = Yii::app()->getController()->renderPartial('/survey/questions/numerical/answer', array(
        'extraclass'             => $extraclass,
        'id'                     => $ia[1],
        'prefix'                 => $prefix,
        'answertypeclass'        => $answertypeclass,
        'tiwidth'                => $tiwidth,
        'fValue'                 => $fValue,
        'checkconditionFunction' => $checkconditionFunction,
        'integeronly'            => $integeronly,
        'maxlength'              => $maxlength,
        'suffix'                 => $suffix,
    ), true);

    $inputnames[]=$ia[1];
    $mandatory=null;
    return array($answer, $inputnames, $mandatory);
}




// ---------------------------------------------------------------
function do_shortfreetext($ia)
{
    global $thissurvey;

    $sGoogleMapsAPIKey  = trim(Yii::app()->getConfig("googleMapsAPIKey"));

    if ($sGoogleMapsAPIKey!='')
    {
        $sGoogleMapsAPIKey = '&key='.$sGoogleMapsAPIKey;
    }

    $extraclass ="";
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if ($aQuestionAttributes['numbers_only']==1)
    {
        $sSeparator             = getRadixPointData($thissurvey['surveyls_numberformat']);
        $sSeparator             = $sSeparator['separator'];
        $extraclass            .= " numberonly";
        $checkconditionFunction = "fixnum_checkconditions";
    }
    else
    {
        $checkconditionFunction = "checkconditions";
    }
    if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
    {
        // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maximum_chars  = intval(trim($aQuestionAttributes['maximum_chars']));
        $maxlength      = "maxlength='{$maximum_chars}' ";
        $extraclass    .=" maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength  = "";
    }

    if (trim($aQuestionAttributes['text_input_width'])!='')
    {
        $tiwidth     = $aQuestionAttributes['text_input_width'];
        $extraclass .= " inputwidth-".trim($aQuestionAttributes['text_input_width']);
        $col         = ($aQuestionAttributes['text_input_width']<=12)?$aQuestionAttributes['text_input_width']:12;
        $extraclass .= " col-sm-".trim($col);
    }
    else
    {
        $tiwidth = 50;
    }
    if (trim($aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $prefix      = $aQuestionAttributes['prefix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withprefix";
    }
    else
    {
        $prefix = '';
    }
    if (trim($aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')
    {
        $suffix      = $aQuestionAttributes['suffix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']];
        $extraclass .= " withsuffix";
    }
    else
    {
        $suffix = '';
    }
    if ($thissurvey['nokeyboard']=='Y')
    {
        includeKeypad();
        $kpclass     = "text-keypad";
        $extraclass .= " inputkeypad";
    }
    else
    {
        $kpclass = "";
    }

    $answer = "";

    if (trim($aQuestionAttributes['display_rows'])!='')
    {
        //question attribute "display_rows" is set -> we need a textarea to be able to show several rows
        $drows = $aQuestionAttributes['display_rows'];

        //if a textarea should be displayed we make it equal width to the long text question
        //this looks nicer and more continuous
        if($tiwidth == 50)
        {
            $tiwidth = 40;
        }
        $dispVal = "";

        if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]])
        {
            $dispVal = str_replace("\\", "", $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]);

            if ($aQuestionAttributes['numbers_only']==1)
            {
                $dispVal = str_replace('.',$sSeparator,$dispVal);
            }
            $dispVal = htmlspecialchars($dispVal);
        }

        $answer .= Yii::app()->getController()->renderPartial('/survey/questions/shortfreetext/textarea/item', array(
            'extraclass'             => $extraclass,
            'freeTextId'             => 'answer'.$ia[1],
            'labelText'              => gT('Your answer'),
            'name'                   => $ia[1],
            'drows'                  => $drows,
            'tiwidth'                => $tiwidth,
            'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
            'dispVal'                => $dispVal,
            'maxlength'              => $maxlength,
            'kpclass'                => $kpclass,
            'prefix'                 => $prefix,
            'suffix'                 => $suffix,
            'sm_col'                 => decide_sm_col($prefix, $suffix)
        ), true);
    }
    elseif((int)($aQuestionAttributes['location_mapservice'])==1)
    {
        $mapservice      = $aQuestionAttributes['location_mapservice'];
        $currentLocation = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]];
        $currentLatLong  = null;
        $floatLat        = 0;
        $floatLng        = 0;

        // Get the latitude/longtitude for the point that needs to be displayed by default
        if (strlen($currentLocation) > 2)
        {
            $currentLatLong = explode(';',$currentLocation);
            $currentLatLong = array($currentLatLong[0],$currentLatLong[1]);
        }
        else
        {
            if ((int)($aQuestionAttributes['location_nodefaultfromip'])==0)
            {
                $currentLatLong = getLatLongFromIp(getIPAddress());
            }

            if (!isset($currentLatLong) || $currentLatLong==false)
            {
                $floatLat = 0;
                $floatLng = 0;
                $LatLong  = explode(" ",trim($aQuestionAttributes['location_defaultcoordinates']));

                if (isset($LatLong[0]) && isset($LatLong[1]))
                {
                    $floatLat = $LatLong[0];
                    $floatLng = $LatLong[1];
                }

                $currentLatLong = array($floatLat,$floatLng);
            }
        }
        // 2 - city; 3 - state; 4 - country; 5 - postal
        $strBuild = "";
        if ($aQuestionAttributes['location_city'])
            $strBuild .= "2";
        if ($aQuestionAttributes['location_state'])
            $strBuild .= "3";
        if ($aQuestionAttributes['location_country'])
            $strBuild .= "4";
        if ($aQuestionAttributes['location_postal'])
            $strBuild .= "5";

        $currentLocation = $currentLatLong[0] . " " . $currentLatLong[1];

        Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."map.js");
        if ($aQuestionAttributes['location_mapservice']==1 && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off")
            Yii::app()->getClientScript()->registerScriptFile("https://maps.googleapis.com/maps/api/js?sensor=false$sGoogleMapsAPIKey");
        else if ($aQuestionAttributes['location_mapservice']==1)
            Yii::app()->getClientScript()->registerScriptFile("http://maps.googleapis.com/maps/api/js?sensor=false$sGoogleMapsAPIKey");
        elseif ($aQuestionAttributes['location_mapservice']==2)
            Yii::app()->getClientScript()->registerScriptFile("http://www.openlayers.org/api/OpenLayers.js");

        $questionHelp = false;
        if (isset($aQuestionAttributes['hide_tip']) && $aQuestionAttributes['hide_tip']==0)
        {
            $questionHelp = true;
            $question_text['help'] = gT('Drag and drop the pin to the desired location. You may also right click on the map to move the pin.');
        }

        $answer = Yii::app()->getController()->renderPartial('/survey/questions/shortfreetext/location_mapservice/item', array(
            'extraclass'             => $extraclass,
            'freeTextId'             => 'answer'.$ia[1],
            'labelText'              => gT('Your answer'),
            'name'                   => $ia[1],
            'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
            'value'                  => $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],
            'kpclass'                => $kpclass,
            'currentLocation'        => $currentLocation,
            'strBuild'               => $strBuild,
            'location_mapservice'    => $aQuestionAttributes['location_mapservice'],
            'location_mapzoom'       => $aQuestionAttributes['location_mapzoom'],
            'location_mapheight'     => $aQuestionAttributes['location_mapheight'],
            'questionHelp'           => $questionHelp,
            'question_text_help'     => $question_text['help'],
            'sm_col'                 => decide_sm_col($prefix, $suffix)
        ), true);

    }
    elseif((int)($aQuestionAttributes['location_mapservice'])==100)
    {
        $currentLocation = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]];
        $currentCenter   = $currentLatLong = null;

        // Get the latitude/longtitude for the point that needs to be displayed by default
        if (strlen($currentLocation) > 2 && strpos($currentLocation,";"))
        {
            $currentLatLong = explode(';',$currentLocation);
            $currentCenter  = $currentLatLong = array($currentLatLong[0],$currentLatLong[1]);
        }
        elseif ((int)($aQuestionAttributes['location_nodefaultfromip'])==0)
        {
            $currentCenter = $currentLatLong = getLatLongFromIp(getIPAddress());
        }

        // If it's not set : set the center to the default position, but don't set the marker
        if (!$currentLatLong)
        {
            $currentLatLong = array("","");
            $currentCenter = explode(" ",trim($aQuestionAttributes['location_defaultcoordinates']));
            if (count($currentCenter)!=2)
            {
                $currentCenter = array("","");
            }
        }
        $strBuild = "";

        $aGlobalMapScriptVar= array(
            'geonameUser'=>getGlobalSetting('GeoNamesUsername'),// Did we need to urlencode ?
            'geonameLang'=>Yii::app()->language,
            );
        $aThisMapScriptVar=array(
            'zoomLevel'=>$aQuestionAttributes['location_mapzoom'],
            'latitude'=>$currentCenter[0],
            'longitude'=>$currentCenter[1],

        );
        App()->getClientScript()->registerPackage('leaflet');
        Yii::app()->getClientScript()->registerScript('sGlobalMapScriptVar',"LSmap=".ls_json_encode($aGlobalMapScriptVar).";\nLSmaps= new Array();",CClientScript::POS_HEAD);
        Yii::app()->getClientScript()->registerScript('sThisMapScriptVar'.$ia[1],"LSmaps['{$ia[1]}']=".ls_json_encode($aThisMapScriptVar),CClientScript::POS_HEAD);
        Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."map.js");
        Yii::app()->getClientScript()->registerCssFile(Yii::app()->getConfig('publicstyleurl') . 'map.css');


        if (isset($aQuestionAttributes['hide_tip']) && $aQuestionAttributes['hide_tip']==0)
        {
            $questionHelp = true;
            $question_text['help'] = gT('Click to set the location or drag and drop the pin. You may may also enter coordinates');
        }

        $itemDatas = array(
            'extraclass'=>$extraclass,
            'name'=>$ia[1],
            'checkconditionFunction'=>$checkconditionFunction.'(this.value, this.name, this.type)',
            'value'=>$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],
            'strBuild'=>$strBuild,
            'location_mapservice'=>$aQuestionAttributes['location_mapservice'],
            'location_mapzoom'=>$aQuestionAttributes['location_mapzoom'],
            'location_mapheight'=>$aQuestionAttributes['location_mapheight'],
            'questionHelp'=>$questionHelp,
            'question_text_help'=>$question_text['help'],
            'location_value'=> $currentLatLong[0].' '.$currentLatLong[1],
            'currentLat'=>$currentLatLong[0],
            'currentLong'=>$currentLatLong[1],
        );
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/shortfreetext/location_mapservice/item_100', $itemDatas, true);
    }
    else
    {
        //no question attribute set, use common input text field
        $dispVal = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]];
        if ($aQuestionAttributes['numbers_only']==1)
        {
            $dispVal = str_replace('.',$sSeparator,$dispVal);
        }
        $dispVal = htmlspecialchars($dispVal,ENT_QUOTES,'UTF-8');

        $itemDatas = array(
            'extraclass'=>$extraclass,
            'name'=>$ia[1],
            'checkconditionFunction'=>$checkconditionFunction.'(this.value, this.name, this.type)',
            'prefix'=>$prefix,
            'suffix'=>$suffix,
            'kpclass'=>$kpclass,
            'tiwidth'=>$tiwidth,
            'dispVal'=>$dispVal,
            'maxlength'=>$maxlength,
            'sm_col' => decide_sm_col($prefix, $suffix)
        );
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/shortfreetext/text/item', $itemDatas, true);

    }

    if (trim($aQuestionAttributes['time_limit'])!='')
    {
        $answer .= return_timer_script($aQuestionAttributes, $ia, "answer".$ia[1]);
    }

    $inputnames = array();
    $inputnames[]=$ia[1];
    return array($answer, $inputnames);

}

function getLatLongFromIp($sIPAddress){
    $ipInfoDbAPIKey = Yii::app()->getConfig("ipInfoDbAPIKey");
    if($ipInfoDbAPIKey)// ipinfodb.com need a key
    {
        $oXML = simplexml_load_file("http://api.ipinfodb.com/v3/ip-city/?key=$ipInfoDbAPIKey&ip=$sIPAddress&format=xml");
        if ($oXML->{'statusCode'} == "OK"){
            $lat = (float)$oXML->{'latitude'};
            $lng = (float)$oXML->{'longitude'};

            return(array($lat,$lng));
        }
        else
            return false;
    }
}



// ---------------------------------------------------------------
function do_longfreetext($ia)
{
    global $thissurvey;
    $extraclass = "";
    if ($thissurvey['nokeyboard']=='Y')
    {
        includeKeypad();
        $kpclass     = "text-keypad";
        $extraclass .=" inputkeypad";
    }
    else
    {
        $kpclass = "";
    }

    $checkconditionFunction = "checkconditions";
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
    {
        // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maximum_chars = intval(trim($aQuestionAttributes['maximum_chars']));
        $maxlength     = "maxlength='{$maximum_chars}' ";
        $extraclass   .= " maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength = "";
    }

    if (trim($aQuestionAttributes['display_rows'])!='')
    {
        $drows = $aQuestionAttributes['display_rows'];
    }
    else
    {
        $drows=5;
    }

    if (trim($aQuestionAttributes['text_input_width'])!='')
    {
        $tiwidth     = $aQuestionAttributes['text_input_width'];
        $extraclass .= " inputwidth-".trim($aQuestionAttributes['text_input_width']);
        $col         = ($aQuestionAttributes['text_input_width']<=12)?$aQuestionAttributes['text_input_width']:12;
        $extraclass .= " col-sm-".trim($col);
    }
    else
    {
        $tiwidth = 40;
    }

    $dispVal = ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]])?htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]):'';

    $answer = Yii::app()->getController()->renderPartial('/survey/questions/longfreetext/answer', array(
        'extraclass'             => $extraclass,
        'kpclass'                => $kpclass,
        'name'                   => $ia[1],
        'drows'                  => $drows,
        'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
        'dispVal'                => $dispVal,
        'tiwidth'                => $tiwidth,
        'maxlength'              => $maxlength,
    ), true);


    if (trim($aQuestionAttributes['time_limit'])!='')
    {
        $answer .= return_timer_script($aQuestionAttributes, $ia, "answer".$ia[1]);
    }

    $inputnames = array();
    $inputnames[]=$ia[1];
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_hugefreetext($ia)
{
    global $thissurvey;
    $extraclass ="";
    if ($thissurvey['nokeyboard']=='Y')
    {
        includeKeypad();
        $kpclass = "text-keypad";
        $extraclass .=" inputkeypad";
    }
    else
    {
        $kpclass = "";
    }

    $checkconditionFunction = "checkconditions";

    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
    {
        // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maximum_chars= intval(trim($aQuestionAttributes['maximum_chars']));
        $maxlength= "maxlength='{$maximum_chars}' ";
        $extraclass .=" maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength= "";
    }

    if (trim($aQuestionAttributes['display_rows'])!='')
    {
        $drows=$aQuestionAttributes['display_rows'];
    }
    else
    {
        $drows=30;
    }
    if (trim($aQuestionAttributes['text_input_width'])!='')
    {
        $tiwidth=$aQuestionAttributes['text_input_width'];
        $extraclass .=" inputwidth-".trim($aQuestionAttributes['text_input_width']);
        $col = ($aQuestionAttributes['text_input_width']<=12)?$aQuestionAttributes['text_input_width']:12;
        $extraclass .=" col-sm-".trim($col);
    }
    else
    {
        $tiwidth=70;
    }
    $dispVal="";
    if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]])
    {
        $dispVal = htmlspecialchars($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]);
    }

    $itemDatas = array(
        'extraclass'=>$extraclass,
        'kpclass'=>$kpclass,
        'name'=>$ia[1],
        'drows'=>$drows,
        'checkconditionFunction'=>$checkconditionFunction.'(this.value, this.name, this.type)',
        'dispVal'=>$dispVal,
        'tiwidth'=>$tiwidth,
        'maxlength'=>$maxlength,
    );
    $answer = Yii::app()->getController()->renderPartial('/survey/questions/longfreetext/answer', $itemDatas, true);

    if (trim($aQuestionAttributes['time_limit']) != '')
    {
        $answer .= return_timer_script($aQuestionAttributes, $ia, "answer".$ia[1]);
    }

    $inputnames = array();
    $inputnames[]=$ia[1];
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_yesno($ia)
{
    $checkconditionFunction = "checkconditions";

    $yChecked = $nChecked = $naChecked = '';
    if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == 'Y')
    {
        $yChecked = CHECKED;
    }

    if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == 'N')
    {
        $nChecked = CHECKED;
    }

    $noAnswer = false;
    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
    {
        $noAnswer = true;
        if (empty($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]]))
        {
            $naChecked = CHECKED;
        }
    }

    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $displayType = $aQuestionAttributes['display_type'];
    $noAnswer = (isset($noAnswer))?$noAnswer:false;
    $itemDatas = array(
        'name'=>$ia[1],
        'yChecked' => $yChecked,
        'nChecked' => $nChecked,
        'naChecked'=> $naChecked,
        'noAnswer' => $noAnswer,
        'checkconditionFunction'=>$checkconditionFunction.'(this.value, this.name, this.type)',
        'value' => $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],
        'displayType'=>$displayType,
    );
    if($displayType===0)
    {
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/yesno/buttons/item', $itemDatas, true);
    }
    else
    {
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/yesno/radio/item', $itemDatas, true);
    }

    $inputnames[]=$ia[1];
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_gender($ia)
{
    $checkconditionFunction = "checkconditions";
    $fChecked               = ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == 'F')?'CHECKED':'';
    $mChecked               = ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == 'M')?'CHECKED':'';
    $naChecked              = '';
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $displayType            = $aQuestionAttributes['display_type'];

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
    {
        $noAnswer = true;
        if ($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]] == '')
        {
            $naChecked = CHECKED;
        }
    }

    $noAnswer = (isset($noAnswer))?$noAnswer:false;

    $itemDatas = array(
        'name'                   => $ia[1],
        'fChecked'               => $fChecked,
        'mChecked'               => $mChecked,
        'naChecked'              => $naChecked,
        'noAnswer'               => $noAnswer,
        'checkconditionFunction' => $checkconditionFunction.'(this.value, this.name, this.type)',
        'value'                  => $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$ia[1]],
    );

    if ($displayType===0)
    {
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/gender/buttons/answer', $itemDatas, true);
    }
    else
    {
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/gender/radio/answer', $itemDatas, true);
    }

    $inputnames   = array();
    $inputnames[] = $ia[1];

    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
/**
* Construct answer part array_5point
* @param $ia
* @return unknown_type
*/
// TMSW TODO - Can remove DB query by passing in answer list from EM
function do_array_5point($ia)
{
    global $thissurvey;
    $aLastMoveResult         = LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ = ($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $extraclass              = "";
    $caption                 = gT("An array with sub-question on each line. The answers are value from 1 to 5 and are contained in the table header. ");
    $checkconditionFunction  = "checkconditions";
    $aQuestionAttributes     = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    if (trim($aQuestionAttributes['answer_width'])!='')
    {
        $answerwidth = $aQuestionAttributes['answer_width'];
        $extraclass .= " answerwidth-".trim($aQuestionAttributes['answer_width']);
    }
    else
    {
        $answerwidth = 50;
    }
    $cellwidth  = 5; // number of columns

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        ++$cellwidth; // add another column
    }

    $cellwidth  = round((( 100 - $answerwidth ) / $cellwidth) , 1); // convert number of columns to percentage of table width
    $sQuery     = "SELECT question FROM {{questions}} WHERE parent_qid=".$ia[0]." AND question like '%|%'";
    $iCount     = Yii::app()->db->createCommand($sQuery)->queryScalar();

    if ($iCount>0)
    {
        $right_exists = true;
        $answerwidth  = $answerwidth/2;
    }
    else
    {
        $right_exists = false;
    }


    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
    }

    $ansresult     = dbExecuteAssoc($ansquery);     //Checked
    $aSubquestions = $ansresult->readAll();
    $anscount      = count($aSubquestions);
    $fn            = 1;
    $sColumns       = $sHeaders = $sRows = $answer_tds = '';


    for ($xc=1; $xc<=5; $xc++)
    {
        $sColumns  .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/columns/col', array('cellwidth'=>$cellwidth), true);
    }

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        $sColumns  .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/columns/col', array('cellwidth'=>$cellwidth), true);
    }

    for ($xc=1; $xc<=5; $xc++)
    {
        $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/cells/thead', array(
            'class'=>'th-1',
            'style'=>'',
            'th_content'=>$xc,
        ), true);
    }

    if ($right_exists)
    {
        $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/cells/thead', array(
            'class'=>'',
            'style'=>'width: '.$answerwidth.'%;',
            'th_content'=>'&nbsp;',
        ), true);
    }

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/cells/thead', array(
            'class'=>'th-2',
            'style'=>'',
            'th_content'=>gT('No answer'),
        ), true);
    }


    $answer_t_content = '';
    $n=0;

    foreach ($aSubquestions as $j => $ansrow)
    {
        $myfname = $ia[1].$ansrow['title'];
        $answertext = $ansrow['question'];
        if (strpos($answertext,'|'))
        {
            $answertext=substr($answertext,0,strpos($answertext,'|'));
        }

        /* Check if this item has not been answered */
        $error = ($ia[6]=='Y' && in_array($myfname,$aMandatoryViolationSubQ))?true:false;

        /* Check for array_filter  */
        $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

        // Value
        $value = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname])) ? $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] : '';

        // ==> tds
        for ($i=1; $i<=5; $i++)
        {
            $CHECKED = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == $i)?'CHECKED':'';
            $answer_tds .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/cells/answer_td_input', array(
                'i'=>$i,
                'myfname'=>$myfname,
                'CHECKED'=>$CHECKED,
                'checkconditionFunction'=>$checkconditionFunction,
                'value'=>$i,
            ), true);
        }

        // => tds
        $answertext2 = $ansrow['question'];
        if (strpos($answertext2,'|'))
        {
            $answertext2=substr($answertext2,strpos($answertext2,'|')+1);
            $answer_tds .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/cells/answer_td_answertext', array(
                'answerwidth'=>$answerwidth,
                'answertext2'=>$answertext2,
            ), true);
        }
        elseif ($right_exists)
        {
            $answer_tds .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/cells/answer_td_answertext', array(
                'answerwidth'=>$answerwidth,
                'answertext2'=>'&nbsp;',
            ), true);
        }

        // ==>tds
        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
        {
            $CHECKED = (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == '')?'CHECKED':'';
            $answer_tds .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/cells/answer_td_input', array(
                'i'=>gT("No answer"),
                'myfname'=>$myfname,
                'CHECKED'=>$CHECKED,
                'checkconditionFunction'=>$checkconditionFunction,
                'value'=>'',
            ), true);

        }

        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/rows/answer_row', array(
                    'answer_tds'=>$answer_tds,
                    'myfname'=>$myfname,
                    'answerwidth'=>$answerwidth,
                    'answertext'=>$answertext,
                    'value'=>$value,
                    'error'=>$error,
                    'sDisplayStyle'=>$sDisplayStyle,
                    'zebra' => 2 - ($j % 2)
                ), true);

        $answer_tds = '';
        $fn++;
        $inputnames[]=$myfname;
    }

    $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/5point/answer', array(
                'extraclass'=>$extraclass,
                'sColumns'=>$sColumns,
                'sHeaders'=>$sHeaders,
                'sRows'=>$sRows,
            ), true);

    //$answer .= $answer_t_content;
    return array($answer, $inputnames);
}




// ---------------------------------------------------------------
/**
* Construct answer part array_10point
* @param $ia
* @return unknown_type
*/
// TMSW TODO - Can remove DB query by passing in answer list from EM
function do_array_10point($ia)
{
    global $thissurvey;
    $aLastMoveResult=LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ=($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $extraclass ="";

    $caption=gT("An array with sub-question on each line. The answers are value from 1 to 10 and are contained in the table header. ");
    $checkconditionFunction = "checkconditions";

    $qquery = "SELECT other FROM {{questions}} WHERE qid=".$ia[0]."  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."'";
    $other = Yii::app()->db->createCommand($qquery)->queryScalar(); //Checked

    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    if (trim($aQuestionAttributes['answer_width'])!='')
    {
        $answerwidth=$aQuestionAttributes['answer_width'];
    }
    else
    {
        $answerwidth = 20;
    }
    $cellwidth  = 10; // number of columns
    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        ++$cellwidth; // add another column
        $caption.=gT("The last cell are for no answer. ");
    }
    $cellwidth = round((( 100 - $answerwidth ) / $cellwidth) , 1); // convert number of columns to percentage of table width

    if ($aQuestionAttributes['random_order']==1) {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
    }
    $ansresult = dbExecuteAssoc($ansquery);   //Checked
    $aSubquestions = $ansresult->readAll();
    $anscount = count($aSubquestions);

    $fn = 1;

    $odd_even = '';

    $sColumns = '';
    for ($xc=1; $xc<=10; $xc++)
    {
        $odd_even = alternation($odd_even);
        $sColumns .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/columns/col', array('odd_even'=>$odd_even,'cellwidth'=>$cellwidth), true);
    }

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        $odd_even = alternation($odd_even);
        $sColumns .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/columns/col', array('odd_even'=>$odd_even,'cellwidth'=>$cellwidth), true);
    }

    $sHeaders = '';
    for ($xc=1; $xc<=10; $xc++)
    {
        $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/rows/cells/thead', array(
            'class'=>'th-3',
            'style'=>'',
            'th_content'=>$xc,
        ), true);
    }

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/rows/cells/thead', array(
            'class'=>'th-4',
            'style'=>'',
            'th_content'=>gT('No answer'),
        ), true);
    }

    $answer_t_content = '';
    $trbc = '';

    $sRows = '';
    foreach ($aSubquestions as $j => $ansrow)
    {
        $myfname = $ia[1].$ansrow['title'];
        $answertext = $ansrow['question'];
        /* Check if this item has not been answered */
        $error = ($ia[6]=='Y' && in_array($myfname, $aMandatoryViolationSubQ) )?true:false;
        $trbc = alternation($trbc , 'row');

        //Get array filter stuff
        //list($htmltbody2, $hiddenfield)=return_array_filter_strings($ia, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, $trbc, $myfname,"tr","$trbc answers-list radio-list");
        $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

        // Value
        $value = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname])) ? $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] : '';

        $answer_tds = '';
        for ($i=1; $i<=10; $i++)
        {
            $CHECKED = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == $i)?'CHECKED':'';

            $answer_tds .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/rows/cells/answer_td_input', array(
                'i'=>$i,
                'myfname'=>$myfname,
                'CHECKED'=>$CHECKED,
                'checkconditionFunction'=>$checkconditionFunction,
                'value'=>$i,
            ), true);
        }

        if ($ia[6] != "Y" && SHOW_NO_ANSWER == 1)
        {
            $CHECKED = (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == '')?'CHECKED':'';
            $answer_tds .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/rows/cells/answer_td_input', array(
                'i'=>gT("No answer"),
                'myfname'=>$myfname,
                'CHECKED'=>$CHECKED,
                'checkconditionFunction'=>$checkconditionFunction,
                'value'=>'',
            ), true);
        }

        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/rows/answer_row', array(
                    'myfname'       => $myfname,
                    'answerwidth'   => $answerwidth,
                    'answertext'    => $answertext,
                    'value'         => $value,
                    'error'         => $error,
                    'sDisplayStyle' => $sDisplayStyle,
                    'zebra'         => 2 - ($j % 2),
                    'answer_tds'    => $answer_tds,
                ), true);

        $inputnames[]=$myfname;
        $fn++;
    }

    $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/10point/answer', array(
                        'extraclass'    => $extraclass,
                        'answerwidth'   => $answerwidth,
                        'sColumns'      => $sColumns,
                        'sHeaders'      => $sHeaders,
                        'sRows'         => $sRows,
                    ),
                true);
    return array($answer, $inputnames);
}


function do_array_yesnouncertain($ia)
{
    global $thissurvey;
    $aLastMoveResult         = LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ = ($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $extraclass              = "";
    $caption                 = gT("An array with sub-question on each line. The answers are yes, no, uncertain and are in the table header. ");
    $checkconditionFunction  = "checkconditions";
    $qquery                  = "SELECT other FROM {{questions}} WHERE qid=".$ia[0]." AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."'";
    $qresult                 = dbExecuteAssoc($qquery);    //Checked
    $qrow                    = $qresult->readAll();
    $other                   = isset($qrow['other']) ? $qrow['other'] : '';
    $aQuestionAttributes     = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $answerwidth             = (trim($aQuestionAttributes['answer_width'])!='')?$aQuestionAttributes['answer_width']:20;
    $cellwidth               = 3; // number of columns

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        ++$cellwidth; // add another column
        $caption.=gT("The last cell are for no answer. ");
    }

    $cellwidth = round((( 100 - $answerwidth ) / $cellwidth) , 1); // convert number of columns to percentage of table width

    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
    }

    $ansresult      = dbExecuteAssoc($ansquery);    //Checked
    $aSubquestions  = $ansresult->readAll();
    $anscount       = count($aSubquestions);
    $fn             = 1;

    $odd_even = '';
    $sColumns = '';

    for ($xc=1; $xc<=3; $xc++)
    {
        $odd_even  = alternation($odd_even);
        $sColumns .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/yesnouncertain/columns/col', array('odd_even'=>$odd_even,'cellwidth'=>$cellwidth), true);
    }

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        $odd_even  = alternation($odd_even);
        $sColumns .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/yesnouncertain/columns/col', array('odd_even'=>$odd_even,'cellwidth'=>$cellwidth, 'no_answer'=>true ), true);
    }

    $no_answer = ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)?true:false;
    $sHeaders  = Yii::app()->getController()->renderPartial('/survey/questions/arrays/yesnouncertain/rows/cells/thead', array('no_answer'=>$no_answer, 'anscount'=>$anscount), true);

    $answer_t_content = '';
    if ($anscount==0)
    {
        $inputnames=array();
    }
    else
    {
        $sRows = '';

        foreach ($aSubquestions as $i => $ansrow)
        {
            $myfname = $ia[1].$ansrow['title'];
            $answertext = $ansrow['question'];
            /* Check the sub question mandatory violation */
            $error = ($ia[6]=='Y' && in_array($myfname, $aMandatoryViolationSubQ))?true:false;

            // Get array_filter stuff
            $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);
            $no_answer = ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)?true:false;
            $value     = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname])) ? $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] : '';
            $Ychecked  = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'Y')?'CHECKED':'';
            $Uchecked  = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'U')?'CHECKED':'';
            $Nchecked  = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'N')?'CHECKED':'';
            $NAchecked = (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == '')?'CHECKED':'';

            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/yesnouncertain/rows/answer_row', array(
                        'myfname'                => $myfname,
                        'sDisplayStyle'          => $sDisplayStyle,
                        'answertext'             => $answertext,
                        'Ychecked'               => $Ychecked,
                        'Uchecked'               => $Uchecked,
                        'Nchecked'               => $Nchecked,
                        'NAchecked'              => $NAchecked,
                        'value'                  => $value,
                        'checkconditionFunction' => $checkconditionFunction,
                        'error'                  => $error,
                        'no_answer'              => $no_answer,
                        'zebra'                  => 2 - ($i % 2)
                    ), true);
            $inputnames[]=$myfname;
            $fn++;
        }
    }

    $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/yesnouncertain/answer', array(
                    'answerwidth' => $answerwidth,
                    'extraclass'  => $extraclass,
                    'sColumns'    => $sColumns,
                    'sHeaders'    => $sHeaders,
                    'sRows'       => $sRows,
                    'anscount'    => $anscount,
                ), true);

    return array($answer, $inputnames);
}


function do_array_increasesamedecrease($ia)
{
    global $thissurvey;
    $aLastMoveResult         = LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ = ($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $extraclass              = "";
    $caption                 = gT("An array with sub-question on each line. The answers are increase, same, decrease and are contained in the table header. ");
    $checkconditionFunction  = "checkconditions";
    $qquery                  = "SELECT other FROM {{questions}} WHERE qid=".$ia[0]." AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."'";
    $qresult                 = dbExecuteAssoc($qquery);   //Checked
    $aQuestionAttributes     = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $answerwidth             = (trim($aQuestionAttributes['answer_width'])!='')?$aQuestionAttributes['answer_width']:20;
    $cellwidth               = 3; // number of columns

    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        ++$cellwidth; // add another column
        $caption.=gT("The last cell are for no answer. ");
    }

    $cellwidth = round((( 100 - $answerwidth ) / $cellwidth) , 1); // convert number of columns to percentage of table width

    foreach ($qresult->readAll() as $qrow)
    {
        $other = $qrow['other'];
    }

    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
    }

    $ansresult      = dbExecuteAssoc($ansquery);  //Checked
    $aSubquestions  = $ansresult->readAll();
    $anscount       = count($aSubquestions);
    $fn             = 1;
    $odd_even       = '';
    $sColumns       = "";

    for ($xc=1; $xc<=3; $xc++)
    {
        $odd_even  = alternation($odd_even);
        $sColumns .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/increasesamedecrease/columns/col', array('odd_even'=>$odd_even,'cellwidth'=>$cellwidth), true);
    }
    if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
    {
        $odd_even  = alternation($odd_even);
        $sColumns .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/increasesamedecrease/columns/col', array('odd_even'=>$odd_even,'cellwidth'=>$cellwidth), true);
    }

    $no_answer  = ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)?true:false; //Question is not mandatory

    $sHeaders        = Yii::app()->getController()->renderPartial('/survey/questions/arrays/increasesamedecrease/rows/cells/thead', array('no_answer'=>$no_answer), true);

    $trbc            = '';
    $answer_body     = '';

    // rows
    $sRows = '';
    foreach ($aSubquestions as $i => $ansrow)
    {
        $myfname        = $ia[1].$ansrow['title'];
        $answertext     = $ansrow['question'];
        $error          = ($ia[6]=='Y' && in_array($myfname, $aMandatoryViolationSubQ))?true:false; /* Check the sub Q mandatory violation */
        $sDisplayStyle  = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);
        $value          = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))?$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]:'';
        $Ichecked       = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'I')?'CHECKED':'';
        $Schecked       = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'S')?'CHECKED':'';
        $Dchecked       = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == 'D')?'CHECKED':'';
        $NAchecked      = (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == '')?'CHECKED':'';
        $no_answer      = ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)?true:false;

        $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/increasesamedecrease/rows/answer_row', array(
                    'myfname'=> $myfname,
                    'sDisplayStyle'=> $sDisplayStyle,
                    'answertext'=> $answertext,
                    'Ichecked'=>$Ichecked,
                    'Schecked'=> $Schecked,
                    'Dchecked'=>$Dchecked,
                    'NAchecked'=>$NAchecked,
                    'value'=>$value,
                    'checkconditionFunction'=>$checkconditionFunction,
                    'error'=>$error,
                    'no_answer'=>$no_answer,
                    'zebra' => 2 - ($i % 2)
                ), true);

        $inputnames[]=$myfname;
        $fn++;
    }

    $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/increasesamedecrease/answer', array(
        'extraclass' => $extraclass,
        'answerwidth'=> $answerwidth,
        'sColumns'   => $sColumns,
        'sHeaders'   => $sHeaders,
        'sRows'      => $sRows,
        'anscount'   => $anscount,
    ), true);

    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
// TMSW TODO - Can remove DB query by passing in answer list from EM
function do_array($ia)
{
    global $thissurvey;
    $aLastMoveResult         = LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ = ($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $repeatheadings          = Yii::app()->getConfig("repeatheadings");
    $minrepeatheadings       = Yii::app()->getConfig("minrepeatheadings");
    $extraclass              = "";
    $caption                 = "";// Just leave empty, are replaced after
    $checkconditionFunction  = "checkconditions";
    $qquery                  = "SELECT other FROM {{questions}} WHERE qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."'";
    $other                   = Yii::app()->db->createCommand($qquery)->queryScalar(); //Checked
    $aQuestionAttributes     = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $answerwidth             = (trim($aQuestionAttributes['answer_width'])!='')?$aQuestionAttributes['answer_width']:20;
    $columnswidth            = 100-$answerwidth;

    if ($aQuestionAttributes['use_dropdown'] == 1)
    {
        $useDropdownLayout = true;
        $extraclass       .= " dropdown-list";
        $caption           = gT("An array with sub-question on each line. You have to select your answer.");
    }
    else
    {
        $useDropdownLayout = false;
        $caption           = gT("An array with sub-question on each line. The answers are contained in the table header. ");
    }

    if (ctype_digit(trim($aQuestionAttributes['repeat_headings'])) && trim($aQuestionAttributes['repeat_headings']!=""))
    {
        $repeatheadings    = intval($aQuestionAttributes['repeat_headings']);
        $minrepeatheadings = 0;
    }

    $lresult    = Answer::model()->findAll(array('order'=>'sortorder, code', 'condition'=>'qid=:qid AND language=:language AND scale_id=0', 'params'=>array(':qid'=>$ia[0],':language'=>$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang'])));
    $labelans   = array();
    $labelcode  = array();

    foreach ($lresult as $lrow)
    {
        $labelans[]  = $lrow->answer;
        $labelcode[] = $lrow->code;
    }

    // No-dropdown layout
    if ($useDropdownLayout === false && count($lresult) > 0)
    {
        $sQuery = "SELECT count(qid) FROM {{questions}} WHERE parent_qid={$ia[0]} AND question like '%|%' ";
        $iCount = Yii::app()->db->createCommand($sQuery)->queryScalar();

        if ($iCount>0)
        {
            $right_exists = true;
            $answerwidth  = $answerwidth/2;
        }
        else
        {
            $right_exists = false;
        }
        // $right_exists is a flag to find out if there are any right hand answer parts. If there arent we can leave out the right td column
        if ($aQuestionAttributes['random_order']==1)
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
        }
        else
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
        }

        $ansresult  = dbExecuteAssoc($ansquery); //Checked
        $aQuestions = $ansresult->readAll();
        $anscount   = count($aQuestions);
        $fn         = 1;
        $numrows    = count($labelans);

        if ($right_exists)
        {
            ++$numrows;
            $caption .= gT("After answers, a cell give some information. ");
        }
        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
        {
            ++$numrows;
            $caption .= gT("The last cell are for no answer. ");
        }

        $cellwidth = round( ($columnswidth / $numrows ) , 1 );

        $sHeaders = Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/thead', array(
            'class'   => '',
            'content' => '&nbsp;',
        ),  true);

        foreach ($labelans as $ld)
        {
            $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/thead', array(
                'class'   => 'th-9',
                'content' => $ld,
            ),  true);
        }

        if ($right_exists)
        {
            $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/thead', array(
                'class'     => '',
                'content'   => '&nbsp;',
            ),  true);
        }

        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory and we can show "no answer"
        {
            $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/thead', array(
                'class'   => 'th-10',
                'content' => gT('No answer'),
            ),  true);
        }

        $answer     = '';
        $trbc       = '';
        $inputnames = array();

        $sRows = '';
        foreach ($aQuestions as $i => $ansrow)
        {
            if (isset($repeatheadings) && $repeatheadings > 0 && ($fn-1) > 0 && ($fn-1) % $repeatheadings == 0)
            {
                if ( ($anscount - $fn + 1) >= $minrepeatheadings )
                {
                    // Close actual body and open another one
                    $sRows .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/repeat_header', array(
                                'sHeaders'=>$sHeaders
                            ),  true);
                }
            }

            $myfname        = $ia[1].$ansrow['title'];
            $answertext     = $ansrow['question'];
            $answertext     = (strpos($answertext,'|') !== false) ? substr($answertext,0, strpos($answertext,'|')) : $answertext;
            $answerwidth    = (strpos($answertext,'|') !== false) ? $answerwidth/2 : $answerwidth;
            $answertextsave = $answertext;

            if ($right_exists && strpos($ansrow['question'], '|') !== false)
            {
                $answertextright = substr($ansrow['question'], strpos($ansrow['question'], '|') + 1);
            }
            else
            {
                $answertextright = null;
            }

            $error          = (in_array($myfname, $aMandatoryViolationSubQ))?true:false;             /* Check the mandatory sub Q violation */
            $value          = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))? $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] : '';
            $sDisplayStyle  = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);
            $thiskey        = 0;
            $answer_tds     = '';
            $fn++;

            foreach ($labelcode as $ld)
            {
                $CHECKED     = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == $ld)?'CHECKED':'';
                $answer_tds .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/answer_td', array(
                            'myfname'=>$myfname,
                            'ld'=>$ld,
                            'label'=>$labelans[$thiskey],
                            'CHECKED'=>$CHECKED,
                            'checkconditionFunction'=>$checkconditionFunction,
                        ),  true);
                $thiskey++;
            }

            /*
            if (strpos($answertextsave,'|'))
            {
                $answertext        = substr($answertextsave,strpos($answertextsave,'|')+1);

                $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/thead', array(
                    'class'   => 'answertextright',
                    'content' => $answertext,
                ),  true);
            }
            elseif ($right_exists)
            {
                $sHeaders .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/thead', array(
                    'class'   => 'answertextright',
                    'content' => '&nbsp;',
                ),  true);
            }
            */

            // NB: $ia[6] = mandatory
            $no_answer_td = '';
            if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
            {
                $CHECKED = (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == '')?'CHECKED':'';
                $no_answer_td .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/cells/answer_td', array(
                            'myfname'                => $myfname,
                            'ld'                     => '',
                            'label'                  => gT('No answer'),
                            'CHECKED'                => $CHECKED,
                            'checkconditionFunction' => $checkconditionFunction,
                        ),  true);
            }

            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/rows/answer_row', array(
                        'answer_tds' => $answer_tds,
                        'no_answer_td' => $no_answer_td,
                        'myfname'    => $myfname,
                        'answertext' => $answertext,
                        'answertextright' => $right_exists ? $answertextright : null,
                        'right_exists' => $right_exists,
                        'value'      => $value,
                        'error'      => $error,
                        'zebra'      => 2 - ($i % 2)
                    ),  true);
            $inputnames[]=$myfname;
        }


        $odd_even = '';
        $sColumns = '';
        foreach ($labelans as $c)
        {
            $odd_even .= alternation($odd_even);
            $sColumns .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/columns/col', array(
                'class'     => $odd_even,
                'cellwidth' => $cellwidth,
            ), true);
        }

        if ($right_exists)
        {
            $odd_even = alternation($odd_even);
            $sColumns .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/columns/col', array(
                'class'     => 'answertextright '.$odd_even,
                'cellwidth' => $cellwidth,
            ), true);
        }

        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
        {
            $odd_even = alternation($odd_even);
            $sColumns .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/columns/col', array(
                'class'     => 'col-no-answer '.$odd_even,
                'cellwidth' => $cellwidth,
            ), true);
        }

        $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/no_dropdown/answer', array(
            'answerwidth'=> $answerwidth,
            'anscount'   => $anscount,
            'sRows'      => $sRows,
            'extraclass' => $extraclass,
            'sHeaders'   => $sHeaders,
            'sColumns'   => $sColumns,
        ),  true);
    }

    // Dropdown layout
    elseif ($useDropdownLayout === true && count($lresult)> 0)
    {
        foreach($lresult as $lrow)
        {
            $labels[]=array(
                'code'   => $lrow->code,
                'answer' => $lrow->answer
            );
        }

        $sQuery = "SELECT count(question) FROM {{questions}} WHERE parent_qid={$ia[0]} AND question like '%|%' ";
        $iCount = Yii::app()->db->createCommand($sQuery)->queryScalar();

        if ($iCount>0)
        {
            $right_exists = true;
            $answerwidth  = $answerwidth/2;
        }
        else
        {
            $right_exists = false;
        }
        // $right_exists is a flag to find out if there are any right hand answer parts. If there arent we can leave out the right td column
        if ($aQuestionAttributes['random_order']==1)
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
        }
        else
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
        }

        $ansresult  = dbExecuteAssoc($ansquery); //Checked
        $aQuestions = $ansresult->readAll();
        $anscount   = count($aQuestions);
        $fn         = 1;
        $numrows    = count($labels);

        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
        {
            ++$numrows;
        }
        if ($right_exists)
        {
            ++$numrows;
        }

        $cellwidth    = round( ($columnswidth / $numrows ) , 1 );

        $trbc = '';
        $inputnames=array();

        $sRows = "";
        foreach ($aQuestions as $j => $ansrow)
        {
            $myfname        = $ia[1].$ansrow['title'];
            $answertext     = $ansrow['question'];
            $answertext     = (strpos($answertext,'|') !== false) ? substr($answertext,0, strpos($answertext,'|')):$answertext;
            $answerwidth    = (strpos($answertext,'|') !== false) ? $answerwidth/2:$answerwidth;
            $error          = (in_array($myfname, $aMandatoryViolationSubQ))?true:false;             /* Check the mandatory sub Q violation */
            $value          = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))? $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] : '';
            $sDisplayStyle  = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

            if ($right_exists && (strpos($ansrow['question'], '|') !== false))
            {
                $answertextright = substr($ansrow['question'], strpos($ansrow['question'], '|') + 1);
            }
            else
            {
                $answertextright = null;
            }

            $options = array();

            // Dropdown representation is en exception - even if mandatory or  SHOW_NO_ANSWER is disable a neutral option needs to be shown where the mandatory case asks actively
            $options[0]['text']     = ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)?gT('No answer'):gT('Please choose...');
            $options[0]['value']    = ' ';
            $options[0]['selected'] = (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == '')?'SELECTED':'';

            // Other options
            foreach ($labels as $i=>$lrow)
            {
                $y=$i+1; // $options[0] ]defined just above
                $options[$y]['value']     = $lrow['code'];
                $options[$y]['selected']  = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == $lrow['code'])?'SELECTED':'';
                $options[$y]['text']      = flattenText($lrow['answer']);
            }

            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/dropdown/rows/answer_row', array(
                'myfname'                => $myfname,
                'answertext'             => $answertext,
                'value'                  => $value,
                'error'                  => $error,
                'checkconditionFunction' => $checkconditionFunction,
                'right_exists'           => $right_exists,
                'answertextright'        => $answertextright,
                'options'                => $options,
                'zebra'                  => 2 - ($j % 2)
            ),  true);

            $inputnames[]=$myfname;
            $fn++;
        }


        $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/dropdown/answer', array
        (
            'extraclass' => $extraclass,
            'sRows'      => $sRows,
        ),  true);

    }
    else
    {
        $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/array/dropdown/empty', array(), true);
        $inputnames='';
    }
    return array($answer, $inputnames);
}


function do_array_multitext($ia)
{
    global $thissurvey;
    $aLastMoveResult            = LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ    = ($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $repeatheadings             = Yii::app()->getConfig("repeatheadings");
    $minrepeatheadings          = Yii::app()->getConfig("minrepeatheadings");
    $extraclass                 = "";
    $caption                    = gT("An array of sub-question on each cell. The sub-question text are in the table header and concerns line header. ");

    if ($thissurvey['nokeyboard']=='Y')
    {
        includeKeypad();
        $kpclass = "text-keypad";
    }
    else
    {
        $kpclass = "";
    }

    $checkconditionFunction = "checkconditions";
    $sSeparator             = getRadixPointData($thissurvey['surveyls_numberformat']);
    $sSeparator             = $sSeparator['separator'];
    $defaultvaluescript     = "";
    $qquery                 = "SELECT other FROM {{questions}} WHERE qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."'";
    $other                  = Yii::app()->db->createCommand($qquery)->queryScalar(); //Checked
    $aQuestionAttributes    = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $show_grand             = $aQuestionAttributes['show_grand_total'];
    $totals_class           = '';
    $num_class              = '';
    $show_totals            = '';
    $col_total              = '';
    $row_total              = '';
    $total_col              = '';
    $col_head               = '';
    $row_head               = '';
    $grand_total            = '';
    $q_table_id             = '';
    $q_table_id_HTML        = '';

    if (ctype_digit(trim($aQuestionAttributes['repeat_headings'])) && trim($aQuestionAttributes['repeat_headings']!=""))
    {
        $repeatheadings     = intval($aQuestionAttributes['repeat_headings']);
        $minrepeatheadings  = 0;
    }
    if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
    {
        // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maximum_chars   = intval(trim($aQuestionAttributes['maximum_chars']));
        $maxlength       = "maxlength='{$maximum_chars}' ";
        $extraclass     .= " maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength  = "";
    }

    if ($aQuestionAttributes['numbers_only']==1)
    {
        $checkconditionFunction = "fixnum_checkconditions";

        if (in_array($aQuestionAttributes['show_totals'],array("R","C","B")))
        {
            $q_table_id      = 'totals_'.$ia[0];
            $q_table_id_HTML = ' id="'.$q_table_id.'"';
        }
        $num_class   = ' numbers-only';
        $extraclass .= " numberonly";
        $caption    .= gT("Each answer is a number. ");
        $col_head    = '';

        switch ($aQuestionAttributes['show_totals'])
        {
            case 'R':
                $totals_class   = $show_totals = 'row';
                $row_total      = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/td_total', array('empty'=>false),  true);
                $col_head       = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/thead', array('totalText'=>gT('Total'), 'classes'=>''),  true);

                if ($show_grand == true)
                {
                    $row_head    = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/thead', array('totalText'=>gT('Grand total'), 'classes'=>'answertext'),  true);
                    $col_total   = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/columns/col_total', array('empty'=>true),  true);
                    $grand_total = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/td_grand_total', array('empty'=>false),  true);
                };

                $caption    .=gT("The last row shows the total for the column. ");
                break;

            case 'C':
                $totals_class = $show_totals = 'col';
                $col_total    = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/columns/col_total', array('empty'=>false, 'label'=>true),  true);
                $row_head     = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/thead', array('totalText'=>gT('Total'), 'classes'=>'answertext'),  true);

                if ($show_grand == true)
                {
                    $row_total   = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/td_total', array('empty'=>true),  true);
                    $col_head    = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/thead', array('totalText'=>gT('Grand total'), 'classes'=>''),  true);
                    $grand_total = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/td_grand_total', array('empty'=>false),  true);
                };
                $caption    .= gT("The last column shows the total for the row. ");
                break;

            case 'B':
                $totals_class = $show_totals = 'both';
                $row_total    = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/td_total', array('empty'=>false),  true);
                $col_total    = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/columns/col_total', array('empty'=>false, 'label'=>false),  true);
                $col_head     = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/thead', array('totalText'=>gT('Total'), 'classes'=>''),  true);
                $row_head     = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/thead', array('totalText'=>gT('Total'), 'classes'=>'answertext'),  true);

                if ($show_grand == true)
                {
                    $grand_total = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/td_grand_total', array('empty'=>false),  true);
                }
                else
                {
                    $grand_total = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/td_grand_total', array('empty'=>true),  true);
                };

                $caption    .= gT("The last row shows the total for the column and the last column shows the total for the row. ");
                break;
        };

        if (!empty($totals_class))
        {
            $totals_class = ' show-totals '.$totals_class;

            if ($aQuestionAttributes['show_grand_total'])
            {
                $totals_class  .= ' grand';
                $show_grand     = true;
            };
        };
    }

    $answerwidth = (trim($aQuestionAttributes['answer_width'])!='')?$aQuestionAttributes['answer_width']:20;

    if (trim($aQuestionAttributes['text_input_width'])!='')
    {
        $inputwidth  = $aQuestionAttributes['text_input_width'];
        $col         = ($aQuestionAttributes['text_input_width']<=12)?$aQuestionAttributes['text_input_width']:12;
        $extraclass .= " col-sm-".trim($col);
    }
    else
    {
        $inputwidth = 20;
    }

    $columnswidth = 100-($answerwidth*2);
    $lquery       = "SELECT * FROM {{questions}} WHERE parent_qid={$ia[0]}  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and scale_id=1 ORDER BY question_order";
    $lresult      = Yii::app()->db->createCommand($lquery)->query();
    $labelans     = array();
    $labelcode    = array();

    foreach ($lresult->readAll() as $lrow)
    {
        $labelans[]  = $lrow['question'];
        $labelcode[] = $lrow['title'];
    }

    if ($numrows=count($labelans))
    {
        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
        {
            $numrows++;
        }

        if ( ($show_grand == true &&  $show_totals == 'col' ) || $show_totals == 'row' ||  $show_totals == 'both' )
        {
            ++$numrows;
        }

        $cellwidth = $columnswidth/$numrows;
        $cellwidth = sprintf('%02d', $cellwidth);

        $ansquery  = "SELECT count(question) FROM {{questions}} WHERE parent_qid={$ia[0]} and scale_id=0 AND question like '%|%'";
        $ansresult = Yii::app()->db->createCommand($ansquery)->queryScalar(); //Checked

        if ($ansresult>0)
        {
            $right_exists = true;
            $answerwidth  = $answerwidth/2;
            $caption     .= gT("The last cell give some information. ");
        }
        else
        {
            $right_exists = false;
        }

        // $right_exists is a flag to find out if there are any right hand answer parts. If there arent we can leave out the right td column
        if ($aQuestionAttributes['random_order']==1)
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] and scale_id=0 AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
        }
        else
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] and scale_id=0 AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
        }

        $ansresult  = dbExecuteAssoc($ansquery);
        $aQuestions = $ansresult->readAll();
        $anscount   = count($aQuestions);
        $fn         = 1;
        $odd_even   = '';

        $showGrandTotal = ( ($show_grand == true &&  $show_totals == 'col' ) || $show_totals == 'row' ||  $show_totals == 'both' )?true:false;

        $trbc = '';
        $sRows = '';
        foreach ($aQuestions as $j => $ansrow)
        {
            if (isset($repeatheadings) && $repeatheadings > 0 && ($fn-1) > 0 && ($fn-1) % $repeatheadings == 0)
            {
                if ( ($anscount - $fn + 1) >= $minrepeatheadings )
                {
                    // Close actual body and open another one
                    $sRows .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/repeat_header', array(
                                'answerwidth'  => $answerwidth,
                                'labelans'     => $labelans,
                                'right_exists' => $right_exists,
                                'col_head'     => $col_head,
                            ),  true);
                }
            }

            $myfname = $ia[1].$ansrow['title'];
            $answertext = $ansrow['question'];
            $answertextsave=$answertext;
            $error = false;

            if ($ia[6]=='Y' && !empty($aMandatoryViolationSubQ))
            {
                //Go through each labelcode and check for a missing answer! If any are found, highlight this line
                $emptyresult=0;
                foreach($labelcode as $ld)
                {
                    $myfname2=$myfname.'_'.$ld;
                    if(in_array($myfname2, $aMandatoryViolationSubQ))
                    {
                        $emptyresult=1;
                    }
                }
                $error = false;
                if ($emptyresult == 1)
                {
                    $error = true;
                }
            }
            $value          = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))?$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]:'';
            $sDisplayStyle  = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);

            if (strpos($answertext,'|'))
            {
                $answertext=substr($answertext,0, strpos($answertext,'|'));
            }

            $thiskey = 0;
            $answer_tds = '';

            foreach ($labelcode as $ld)
            {
                $myfname2=$myfname."_$ld";
                $myfname2value = isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2]) ? $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2] : "";

                if ($aQuestionAttributes['numbers_only']==1)
                {
                    $myfname2value = str_replace('.',$sSeparator,$myfname2value);
                }

                $inputnames[] =$myfname2;
                $value        = str_replace ('"', "'", str_replace('\\', '', $myfname2value));
                $answer_tds  .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/cells/answer_td', array(
                                    'ld'         => $ld,
                                    'myfname2'   => $myfname2,
                                    'labelText'  => $labelans[$thiskey],
                                    'kpclass'    => $kpclass,
                                    'maxlength'  => $maxlength,
                                    'inputwidth' => $inputwidth,
                                    'value'      => $myfname2value,
                                ),  true);
                $thiskey += 1;
            }

            $rightTd = $rightTdEmpty = false;

            if (strpos($answertextsave,'|'))
            {
                $answertext =substr($answertextsave,strpos($answertextsave,'|')+1);
                $rightTd    = true; $rightTdEmpty = false;
            }
            elseif ($right_exists)
            {
                $rightTd      = true;
                $rightTdEmpty = true;
            }

            $formatedRowTotal = str_replace(array('[[ROW_NAME]]','[[INPUT_WIDTH]]') , array(strip_tags($answertext),$inputwidth) , $row_total);

            $sRows .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/rows/answer_row', array(
                                'myfname'           =>  $myfname,
                                'answertext'        =>  $answertext,
                                'error'             =>  $error,
                                'value'             =>  $value,
                                'answer_tds'        =>  $answer_tds,
                                'rightTd'           =>  $rightTd,
                                'rightTdEmpty'      =>  $rightTdEmpty,
                                'answerwidth'       =>  $answerwidth,
                                'formatedRowTotal'  =>  $formatedRowTotal,
                                'zebra'                  => 2 - ($j % 2)
                            ),  true);

            $fn++;
        }

        $showtotals=false; $total = '';

        if ($show_totals == 'col' || $show_totals == 'both' || $grand_total == true)
        {
            $showtotals = true;

            for ($a = 0; $a < count($labelcode) ; ++$a)
            {
                $total .= str_replace(array('[[ROW_NAME]]','[[INPUT_WIDTH]]') , array(strip_tags($answertext),$inputwidth) , $col_total);

            };
            $total .= str_replace(array('[[ROW_NAME]]','[[INPUT_WIDTH]]') , array(strip_tags($answertext),$inputwidth) , $grand_total);
        }

        $radix = '';

        if (!empty($q_table_id))
        {
            if ($aQuestionAttributes['numbers_only']==1)
            {
                $radix = $sSeparator;
            }
            else
            {
                $radix = 'X';   // to indicate that should not try to change entered values
            }
            Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."array-totalsum.js");
        }

        $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/answer', array(
                    'answerwidth'               => $answerwidth,
                    'col_head'                  => $col_head,
                    'cellwidth'                 => $cellwidth,
                    'labelans'                  => $labelans,
                    'right_exists'              => $right_exists,
                    'showGrandTotal'            => $showGrandTotal,
                    'q_table_id_HTML'           => $q_table_id_HTML,
                    'extraclass'                => $extraclass,
                    'num_class'                 => $num_class,
                    'totals_class'              => $totals_class,
                    'showtotals'                => $showtotals,
                    'row_head'                  => $row_head,
                    'total'                     => $total,
                    'q_table_id'                => $q_table_id,
                    'radix'                     => $radix,
                    'name'                      => $ia[0],
                    'sRows'                     => $sRows,
                    'checkconditionFunction'    => $checkconditionFunction
                ),  true);
    }
    else
    {
        $answer    .= Yii::app()->getController()->renderPartial('/survey/questions/arrays/multitext/empty_error', array(), true);
        $inputnames ='';
    }
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
// TMSW TODO - Can remove DB query by passing in answer list from EM
// Used by array numbers, array_numbers (for searching)
function do_array_multiflexi($ia)
{
    global $thissurvey;

    $answer                     = '';
    $aLastMoveResult            = LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ    = ($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $repeatheadings             = Yii::app()->getConfig("repeatheadings");
    $minrepeatheadings          = Yii::app()->getConfig("minrepeatheadings");
    $extraclass                 = "";
    $answertypeclass            = "";
    $caption                    = gT("An array of sub-question on each cell. The sub-question text are in the table header and concerns line header. ");
    $checkconditionFunction     = "fixnum_checkconditions";
    $defaultvaluescript         = '';
    $qquery                     = "SELECT other FROM {{questions}} WHERE qid=".$ia[0]." AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and parent_qid=0";
    $other                      = Yii::app()->db->createCommand($qquery)->queryScalar(); //Checked

    /*
     * Question Attributes
     */
    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    // Define min and max value
    if (trim($aQuestionAttributes['multiflexible_max'])!='' && trim($aQuestionAttributes['multiflexible_min']) =='')
    {
        $maxvalue    =  $aQuestionAttributes['multiflexible_max'];
        $minvalue    = (isset($minvalue['value']) && $minvalue['value'] == 0)?0:1;
        $extraclass .= " maxvalue maxvalue-".trim($aQuestionAttributes['multiflexible_max']);
    }

    if (trim($aQuestionAttributes['multiflexible_min'])!='' && trim($aQuestionAttributes['multiflexible_max']) =='')
    {
        $minvalue    =  $aQuestionAttributes['multiflexible_min'];
        $maxvalue    = $aQuestionAttributes['multiflexible_min'] + 10;
        $extraclass .= " minvalue minvalue-".trim($aQuestionAttributes['multiflexible_max']);
    }

    if (trim($aQuestionAttributes['multiflexible_min'])=='' && trim($aQuestionAttributes['multiflexible_max']) =='')
    {
        $maxvalue   = 10;
        $minvalue   = (isset($minvalue['value']) && $minvalue['value'] == 0)?0:1;

    }

    if (trim($aQuestionAttributes['multiflexible_min']) !='' && trim($aQuestionAttributes['multiflexible_max']) !='')
    {
        if ($aQuestionAttributes['multiflexible_min'] < $aQuestionAttributes['multiflexible_max'])
        {
            $minvalue   = $aQuestionAttributes['multiflexible_min'];
            $maxvalue   = $aQuestionAttributes['multiflexible_max'];
        }
    }

    $stepvalue = (trim($aQuestionAttributes['multiflexible_step'])!='' && $aQuestionAttributes['multiflexible_step'] > 0)?$aQuestionAttributes['multiflexible_step']:1;

    if($aQuestionAttributes['reverse']==1)
    {
        $tmp        = $minvalue;
        $minvalue   = $maxvalue;
        $maxvalue   = $tmp;
        $reverse    = true;
        $stepvalue  = -$stepvalue;
    }
    else
    {
        $reverse    = false;
    }

    $checkboxlayout = false;
    $inputboxlayout = false;

    if ($aQuestionAttributes['multiflexible_checkbox']!=0)
    {
        $minvalue            =  0;
        $maxvalue            =  1;
        $checkboxlayout      =  true;
        $answertypeclass     =  " checkbox";
        $caption            .= gT("Check or uncheck the answer for each subquestion. ");
    }
    elseif ($aQuestionAttributes['input_boxes']!=0 )
    {
        $inputboxlayout      = true;
        $answertypeclass    .= " numeric-item text";
        $extraclass         .= " numberonly";
        $caption            .= gT("Each answers are a number. ");
    }
    else
    {
        $answertypeclass     = " dropdown";
        $caption            .= gT("Select the answer for each subquestion. ");
    }

    if (ctype_digit(trim($aQuestionAttributes['repeat_headings'])) && trim($aQuestionAttributes['repeat_headings']!=""))
    {
        $repeatheadings     = intval($aQuestionAttributes['repeat_headings']);
        $minrepeatheadings  = 0;
    }

    if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
    {
        // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
        $maximum_chars   = intval(trim($aQuestionAttributes['maximum_chars']));
        $maxlength       = "maxlength='{$maximum_chars}' ";
        $extraclass     .=" maxchars maxchars-".$maximum_chars;
    }
    else
    {
        $maxlength  = "";
    }

    if ($thissurvey['nokeyboard']=='Y')
    {
        includeKeypad();
        $kpclass     = " num-keypad";
        $extraclass .=" inputkeypad";
    }
    else
    {
        $kpclass = "";
    }

    if (trim($aQuestionAttributes['answer_width'])!='')
    {
        $answerwidth    = $aQuestionAttributes['answer_width'];
        $useAnswerWidth = true;
    }
    else
    {
        $answerwidth    = 20;

        // If answerwidth is not given, we want to default to Bootstrap column.
        // Otherwise bug on phone screen.
        $useAnswerWidth = false;
    }

    $columnswidth   = 100-($answerwidth*2);
    $lquery         = "SELECT * FROM {{questions}} WHERE parent_qid={$ia[0]}  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and scale_id=1 ORDER BY question_order";
    $lresult        = dbExecuteAssoc($lquery);
    $aQuestions     = $lresult->readAll();
    $labelans       = array();
    $labelcode      = array();

    foreach ($aQuestions as $lrow)
    {
        $labelans[]  =$lrow['question'];
        $labelcode[] =$lrow['title'];
    }

    if ($numrows=count($labelans))
    {
        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1) {$numrows++;}
        $cellwidth  =   $columnswidth/$numrows;
        $cellwidth  =   sprintf('%02d', $cellwidth);
        $sQuery     = "SELECT count(question) FROM {{questions}} WHERE parent_qid=".$ia[0]." AND scale_id=0 AND question like '%|%'";
        $iCount     = Yii::app()->db->createCommand($sQuery)->queryScalar();

        if ($iCount>0)
        {
            $right_exists    =  true;
            $answerwidth     =  $answerwidth/2;
            $caption        .=  gT("The last cell give some information. ");
        }
        else
        {
            $right_exists   = false;
        }

        // $right_exists is a flag to find out if there are any right hand answer parts. If there arent we can leave out the right td column
        if ($aQuestionAttributes['random_order']==1)
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND scale_id=0 AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
        }
        else
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND scale_id=0 AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
        }

        $ansresult = dbExecuteAssoc($ansquery)->readAll();  //Checked

        if (trim($aQuestionAttributes['parent_order']!=''))
        {
            $iParentQID = (int) $aQuestionAttributes['parent_order'];
            $aResult    = array();
            $sessionao  = $_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['answer_order'];

            foreach ($sessionao[$iParentQID] as $aOrigRow)
            {
                $sCode  = $aOrigRow['title'];

                foreach ($ansresult as $aRow)
                {
                    if ($sCode==$aRow['title'])
                    {
                        $aResult[]=$aRow;
                    }
                }
            }
            $ansresult  = $aResult;
        }
        $anscount = count($ansresult);
        $fn=1;

        $sAnswerRows = '';
        foreach ($ansresult as $j => $ansrow)
        {
            if (isset($repeatheadings) && $repeatheadings > 0 && ($fn-1) > 0 && ($fn-1) % $repeatheadings == 0)
            {
                if ( ($anscount - $fn + 1) >= $minrepeatheadings )
                {
                    $sAnswerRows .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/multiflexi/rows/repeat_header', array(
                                'labelans'      =>  $labelans,
                                'right_exists'  =>  $right_exists,
                                'cellwidth'     =>  $cellwidth,
                                'answerwidth'   =>  $answerwidth,
                            ),  true);
                }
            }

            $myfname        = $ia[1].$ansrow['title'];
            $answertext     = $ansrow['question'];
            $answertextsave = $answertext;

            /* Check the sub Q mandatory violation */
            $error = false;

            if ($ia[6]=='Y' && !empty($aMandatoryViolationSubQ))
            {
                //Go through each labelcode and check for a missing answer! Default :If any are found, highlight this line, checkbox : if one is not found : don't highlight
                // PS : we really need a better system : event for EM !
                $emptyresult    = ($aQuestionAttributes['multiflexible_checkbox']!=0) ? 1 : 0;

                foreach ($labelcode as $ld)
                {
                    $myfname2   = $myfname.'_'.$ld;
                    if ($aQuestionAttributes['multiflexible_checkbox']!=0)
                    {
                        if (!in_array($myfname2, $aMandatoryViolationSubQ))
                        {
                            $emptyresult    = 0;
                        }
                    }
                    else
                    {
                        if (in_array($myfname2, $aMandatoryViolationSubQ))
                        {
                            $emptyresult    =   1;
                        }
                    }
                }

                $error = ($emptyresult == 1)?true:false;
            }

            $sSeparator = getRadixPointData($thissurvey['surveyls_numberformat']);
            $sSeparator = $sSeparator['separator'];

            // Get array_filter stuff
            $sDisplayStyle = return_display_style($ia, $aQuestionAttributes, $thissurvey, $myfname);


            if (strpos($answertext,'|'))
            {
                $answertext =   substr($answertext,0, strpos($answertext,'|'));
            }

            $row_value = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))?$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]:'';

            $first_hidden_field = '';
            $thiskey            = 0;
            $answer_tds         = '';

            foreach ($labelcode as $i => $ld)
            {
                $myfname2   = $myfname."_$ld";
                $value      = (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2]))?$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2]:'';

                if ($checkboxlayout == false)
                {

                    $answer_tds .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/multiflexi/rows/cells/answer_td', array(
                                        'dataTitle'                 => $labelans[$i],
                                        'ld'                        => $ld,
                                        'answertypeclass'           => $answertypeclass,
                                        'answertext'                => $answertext,
                                        'stepvalue'                 => $stepvalue,
                                        'extraclass'                => $extraclass,
                                        'myfname2'                  => $myfname2,
                                        'error'                     => $error,
                                        'inputboxlayout'            => $inputboxlayout,
                                        'checkconditionFunction'    => $checkconditionFunction,
                                        'minvalue'                  => $minvalue,
                                        'maxvalue'                  => $maxvalue,
                                        'reverse'                   => $reverse,
                                        'value'                     => $value,
                                        'sSeparator'                => $sSeparator,
                                        'kpclass'                   => $kpclass,
                                        'maxlength'                 => $maxlength,
                                    ),  true);


                    $inputnames[]=$myfname2;
                    $thiskey++;
                }
                else
                {

                    if(isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname2] == '1')
                    {
                        $myvalue    = '1';
                        $setmyvalue = CHECKED;
                    }
                    else
                    {
                        $myvalue    = '';
                        $setmyvalue = '';
                    }

                    $answer_tds .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/multiflexi/rows/cells/answer_td_checkboxes', array(
                                        'dataTitle'                 => $labelans[$i],
                                        'ld'                        => $ld,
                                        'answertypeclass'           => $answertypeclass,
                                        'value'                     => $myvalue,
                                        'setmyvalue'                => $setmyvalue,
                                        'myfname2'                  => $myfname2,
                                        'checkconditionFunction'    => $checkconditionFunction,
                                        'extraclass'                => $extraclass,
                                    ),  true);
                    $inputnames[]   = $myfname2;
                    $thiskey++;
                }
            }

            $rightTd = false;$answertextright= '';

            if (strpos($answertextsave,'|'))
            {
                $answertextright    = substr($answertextsave,strpos($answertextsave,'|')+1);
                $rightTd            = true;
            }
            elseif ($right_exists)
            {
                $rightTd = true;
            }

            // answer_row
            $sAnswerRows .=  Yii::app()->getController()->renderPartial('/survey/questions/arrays/multiflexi/rows/answer_row', array(
                                'sDisplayStyle'     => $sDisplayStyle,
                                'useAnswerWidth'    => $useAnswerWidth,
                                'answerwidth'       => $answerwidth,
                                'myfname'           => $myfname,
                                'error'             => $error,
                                'row_value'         => $row_value,
                                'answertext'        => $answertext,
                                'answertextright'   => $answertextright,
                                'answer_tds'        => $answer_tds,
                                'rightTd'           => $rightTd,
                                'zebra'                  => 2 - ($j % 2)
                            ),  true);
            $fn++;
        }

        $answer = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multiflexi/answer', array(
                            'answertypeclass'   => $answertypeclass,
                            'extraclass'        => $extraclass,
                            'answerwidth'       => $answerwidth,
                            'labelans'          => $labelans,
                            'cellwidth'         => $cellwidth,
                            'right_exists'      => $right_exists,
                            'sAnswerRows'       => $sAnswerRows,
                        ),  true);

    }
    else
    {
        //$answer = "\n<p class=\"error\">".gT("Error: There are no answer options for this question and/or they don't exist in this language.")."</p>\n";
        $answer     = Yii::app()->getController()->renderPartial('/survey/questions/arrays/multiflexi/empty_error', array(),  true);
        $inputnames = '';
    }
    return array($answer, $inputnames);
}


// ---------------------------------------------------------------
// TMSW TODO - Can remove DB query by passing in answer list from EM
function do_arraycolumns($ia)
{
    $aLastMoveResult=LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ=($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $extraclass = "";
    $checkconditionFunction = "checkconditions";
    $caption=gT("An array with sub-question on each column. The sub-question are on table header, the answers are in each line header. ");

    $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($ia[0]);
    $qquery = "SELECT other FROM {{questions}} WHERE qid=".$ia[0]." AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."'";
    $other = Yii::app()->db->createCommand($qquery)->queryScalar(); //Checked

    $lquery = "SELECT * FROM {{answers}} WHERE qid=".$ia[0]."  AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and scale_id=0 ORDER BY sortorder, code";
    $oAnswers = dbExecuteAssoc($lquery);
    $aAnswers = $oAnswers->readAll();
    $labelans=array();
    $labelcode=array();
    $labels=array();
    foreach ($aAnswers as $lrow)
    {
        $labelans[]=$lrow['answer'];
        $labelcode[]=$lrow['code'];
        $labels[]=array("answer"=>$lrow['answer'], "code"=>$lrow['code']);
    }
    if (count($labelans) > 0)
    {
        if ($ia[6] != 'Y' && SHOW_NO_ANSWER == 1)
        {
            $labelcode[]='';
            $labelans[]=gT('No answer');
            $labels[]=array('answer'=>gT('No answer'), 'code'=>'');
        }
        if ($aQuestionAttributes['random_order']==1) {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY ".dbRandom();
        }
        else
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY question_order";
        }
        $ansresult = dbExecuteAssoc($ansquery);  //Checked
        $aQuestions = $ansresult->readAll();
        $anscount = count($aQuestions);
        if ($anscount>0)
        {
            $fn=1;
            $cellwidth=$anscount;
            $cellwidth=round(( 50 / $cellwidth ) , 1);
            $answer = "\n<table class=\"array-by-columns-table table-array-by-column table question subquestion-list questions-list\">\n"
            . "\t<colgroup class=\"col-responses\">\n"
            //. "\t<col class=\"col-answers\" width=\"50%\" />\n";
            . "\t<col class=\"col-answers\" style='width: 50%' />\n";
            $odd_even = '';
            for( $c = 0 ; $c < $anscount ; ++$c )
            {
                $odd_even = alternation($odd_even);
                $odd_even_well = ($odd_even == 'odd')?$odd_even.' well':$odd_even;
                //$answer .= "<col class=\"$odd_even question-item answers-list radio-list\" width=\"$cellwidth%\" />\n";
                $answer .= "<col class=\"$odd_even_well question-item answers-list radio-list\" style='width: $cellwidth%;' />\n";
            }
            $answer .= "\t</colgroup>\n\n"
            . "\t<thead class='thead-array-by-column'>\n"
            . "<tr>\n"
            . "\t<td>&nbsp;</td>\n";

            foreach ($aQuestions as $ansrow)
            {
                $anscode[]=$ansrow['title'];
                $answers[]=$ansrow['question'];
            }
            $trbc = '';
            $odd_even = '';
            for ($_i=0;$_i<count($answers);++$_i)
            {
                $ld = $answers[$_i];
                $myfname = $ia[1].$anscode[$_i];
                $trbc = alternation($trbc , 'row');
                /* Check the Sub Q mandatory violation */
                if ($ia[6]=='Y' && in_array($myfname, $aMandatoryViolationSubQ))
                {
                    //$ld = "<span class=\"errormandatory\">{$ld}</span>";
                    $ld ='
                                <div class="alert alert-danger " role="alert">'.
                                        $ld
                                    .'
                                </div>';

                }
                $odd_even = alternation($odd_even);
                $answer .= "\t<th class=\"$odd_even\">$ld</th>\n";
            }
            unset($trbc);
            $answer .= "</tr>\n\t</thead>\n\n\t<tbody>\n";
            $ansrowcount=0;
            $ansrowtotallength=0;
            foreach($aQuestions as $ansrow)
            {
                $ansrowcount++;
                $ansrowtotallength=$ansrowtotallength+strlen($ansrow['question']);
            }
            $percwidth=100 - ($cellwidth*$anscount);
            foreach($labels as $ansrow)
            {
                $answer .= "<tr>\n"
                . "\t<th class=\"arraycaptionleft dontread\">{$ansrow['answer']}</th>\n";
                $i = 0;
                foreach ($anscode as $ld)
                {
                    //if (!isset($trbc) || $trbc == 'array1') {$trbc = 'array2';} else {$trbc = 'array1';}
                    $myfname=$ia[1].$ld;

                    $answer .= "\t<td data-title='" . $ansrow['answer'] . "' class=\"answer-cell-7 answer_cell_00$ld answer-item radio-item\">\n"
                    . "\t<label for=\"answer".$myfname.'-'.$ansrow['code']."\"><input class=\"radio\" type=\"radio\" name=\"".$myfname.'" value="'.$ansrow['code'].'" '
                    . 'id="answer'.$myfname.'-'.$ansrow['code'].'" ';
                    if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] == $ansrow['code'])
                    {
                        $answer .= CHECKED;
                    }
                    elseif (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]) && $ansrow['code'] == '')
                    {
                        $answer .= CHECKED;
                        // Humm.. (by lemeur), not sure this section can be reached
                        // because I think $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname] is always set (by save.php ??) !
                        // should remove the !isset part I think !!
                    }
                    $answer .= " onclick=\"$checkconditionFunction(this.value, this.name, this.type)\" />\n"
                    //. "<label class=\"hide read\" for=\"answer".$myfname.'-'.$ansrow['code']."\">{$ansrow['answer']}</label>\n"
                    . "\t</label></td>\n";
                    $i += 1;
                }
                unset($trbc);
                $answer .= "</tr>\n";
                $fn++;
            }

            $answer .= "\t</tbody>\n</table>\n";
            foreach($anscode as $ld)
            {
                $myfname=$ia[1].$ld;
                $answer .= '<input type="hidden" name="java'.$myfname.'" id="java'.$myfname.'" value="';
                if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname]))
                {
                    $answer .= $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname];
                }
                $answer .= "\" />\n";
                $inputnames[]=$myfname;
            }
        }
        else
        {
            $answer = '<p class="error">'.gT('Error: There are no answers defined for this question.')."</p>";
            $inputnames="";
        }
    }
    else
    {
        $answer = "<p class='error'>".gT("Error: There are no answer options for this question and/or they don't exist in this language.")."</p>\n";
        $inputnames = '';
    }
    return array($answer, $inputnames);
}

// ---------------------------------------------------------------
function do_array_dual($ia)
{
    global $thissurvey;
    $aLastMoveResult            = LimeExpressionManager::GetLastMoveResult();
    $aMandatoryViolationSubQ    = ($aLastMoveResult['mandViolation'] && $ia[6] == 'Y') ? explode("|",$aLastMoveResult['unansweredSQs']) : array();
    $repeatheadings             = Yii::app()->getConfig("repeatheadings");
    $minrepeatheadings          = Yii::app()->getConfig("minrepeatheadings");
    $extraclass                 = "";
    $answertypeclass            = ""; // Maybe not
    $caption                    = "";// Just leave empty, are replaced after
    $inputnames                 = array();
    $labelans1                  = array();
    $labelans                   = array();

    /*
     * Get Question Attributes
     */
    $aQuestionAttributes        =  QuestionAttribute::model()->getQuestionAttributes($ia[0]);

    // Get questions and answers by defined order
    if ($aQuestionAttributes['random_order']==1)
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and scale_id=0 ORDER BY ".dbRandom();
    }
    else
    {
        $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$ia[0] AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' and scale_id=0 ORDER BY question_order";
    }

    $ansresult      = dbExecuteAssoc($ansquery);   //Checked
    $aSubQuestions  = $ansresult->readAll();
    $anscount       = count($aSubQuestions);
    $lquery         = "SELECT * FROM {{answers}} WHERE scale_id=0 AND qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY sortorder, code";
    $lresult        = dbExecuteAssoc($lquery); //Checked
    $aAnswersScale0 = $lresult->readAll();
    $lquery1        = "SELECT * FROM {{answers}} WHERE scale_id=1 AND qid={$ia[0]} AND language='".$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']."' ORDER BY sortorder, code";
    $lresult1       = dbExecuteAssoc($lquery1); //Checked
    $aAnswersScale1 = $lresult1->readAll();

    // Set attributes
    if ($aQuestionAttributes['use_dropdown']==1)
    {
        $useDropdownLayout = true;
        $extraclass .=" dropdown-list";
        $answertypeclass .=" dropdown";
        $doDualScaleFunction="doDualScaleDropDown";// javascript funtion to lauch at end of answers
        $caption=gT("An array with sub-question on each line, with 2 answers to provide on each line. You have to select the answer.");
    }
    else
    {
        $useDropdownLayout = false;
        $extraclass .=" radio-list";
        $answertypeclass .=" radio";
        $doDualScaleFunction="doDualScaleRadio";
        $caption=gT("An array with sub-question on each line, with 2 answers to provide on each line. The answers are contained in the table header. ");
    }
    if(ctype_digit(trim($aQuestionAttributes['repeat_headings'])) && trim($aQuestionAttributes['repeat_headings']!=""))
    {
        $repeatheadings = intval($aQuestionAttributes['repeat_headings']);
        $minrepeatheadings = 0;
    }

    $leftheader     = (trim($aQuestionAttributes['dualscale_headerA'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')?$leftheader= $aQuestionAttributes['dualscale_headerA'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']]:'';
    $rightheader    = (trim($aQuestionAttributes['dualscale_headerB'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='')?$aQuestionAttributes['dualscale_headerB'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']]:'';
    $answerwidth    = (trim($aQuestionAttributes['answer_width'])!='')?$aQuestionAttributes['answer_width']:20;

    // Find if we have rigth and center text
    $sQuery         = "SELECT count(question) FROM {{questions}} WHERE parent_qid=".$ia[0]." and scale_id=0 AND question like '%|%'";
    $rigthCount     = Yii::app()->db->createCommand($sQuery)->queryScalar();
    $rightexists    = ($rigthCount>0);// $right_exists: flag to find out if there are any right hand answer parts. leaving right column but don't force with
    $sQuery         = "SELECT count(question) FROM {{questions}} WHERE parent_qid=".$ia[0]." and scale_id=0 AND question like '%|%|%'";
    $centerCount    = Yii::app()->db->createCommand($sQuery)->queryScalar();
    $centerexists   = ($centerCount>0);// $center_exists: flag to find out if there are any center hand answer parts. leaving center column but don't force with

    // Label and code for input
    foreach ($aAnswersScale0 as $lrow)
    {
        $labels0[]=Array('code' => $lrow['code'],
        'title' => $lrow['answer']);
    }
    foreach ($aAnswersScale1 as $lrow)
    {
        $labels1[]=Array('code' => $lrow['code'],
        'title' => $lrow['answer']);
    }

    if (count($aAnswersScale0) > 0 && $anscount)
    {
        $answer = "";
        $fn=1;// Used by repeat_heading
        if ($useDropdownLayout === false)
        {
            $columnswidth = 100 - $answerwidth;
            foreach ($aAnswersScale0 as $lrow)
            {
                $labelans0[]=$lrow['answer'];
                $labelcode0[]=$lrow['code'];
            }
            foreach ($aAnswersScale1 as $lrow)
            {
                $labelans1[]=$lrow['answer'];
                $labelcode1[]=$lrow['code'];
            }
            $numrows=count($labelans0) + count($labelans1);
            // Add needed row and fill some boolean: shownoanswer, rightexists, centerexists
            $shownoanswer=($ia[6] != "Y" && SHOW_NO_ANSWER == 1);
            if($shownoanswer) {
                $numrows++;
                $caption.=gT("The last cell are for no answer. ");
            }
            if($rightexists) {$numrows++;}
            if($centerexists) {$numrows++;}
            $cellwidth=$columnswidth/$numrows;
            $cellwidth=sprintf("%02d", $cellwidth); // No reason to do this, except to leave place for separator ?  But then table can not be the same in all browser

            // Header row and colgroups
            //$mycolumns = "\t<col class=\"col-answers\" width=\"$answerwidth%\" />\n";
            $mycolumns = "\t<col class=\"col-answers\" style='width: $answerwidth%;'/>\n";
            $answer_head_line = "\t<th class=\"header_answer_text\">&nbsp;</th>\n\n";
            $mycolumns .= "\t<colgroup class=\"col-responses group-1\">\n";
            $odd_even = '';
            foreach ($labelans0 as $ld)
            {
                $answer_head_line .= "\t<th  class='th-12'>".$ld."</th>\n";
                $odd_even = alternation($odd_even);
                //$mycolumns .= "<col class=\"$odd_even\" width=\"$cellwidth%\" />\n";
                $mycolumns .= "<col class=\"$odd_even\" style='width: $cellwidth%;' />\n";
            }
            $mycolumns .= "\t</colgroup>\n";
            if (count($labelans1)>0) // if second label set is used
            {
                $separatorwidth=($centerexists)? "style='width:$cellwidth%;' ":"";
                //$separatorwidth='';
                $mycolumns .=  "\t<col class=\"separator\" {$separatorwidth}/>\n";
                $mycolumns .= "\t<colgroup class=\"col-responses group-2\">\n";
                $answer_head_line .= "\n\t<td class=\"header_separator\">&nbsp;</td>\n\n"; // Separator : and No answer for accessibility for first colgroup
                foreach ($labelans1 as $ld)
                {
                    $answer_head_line .= "\t<th  class='th-13'>".$ld."</th>\n";
                    $odd_even = alternation($odd_even);
                    //$mycolumns .= "<col class=\"$odd_even\" width=\"$cellwidth%\" />\n";
                    $mycolumns .= "<col class=\"$odd_even\" style='width: $cellwidth%;' />\n";
                }
                $mycolumns .= "\t</colgroup>\n";
            }
            if($shownoanswer || $rightexists)
            {
                $rigthwidth=($rightexists)? "style='width: $cellwidth%;' ":"";
                //$rigthwidth="";
                $mycolumns .=  "\t<col class=\"separator rigth_separator\" {$rigthwidth}/>\n";
                $answer_head_line .= "\n\t<td class=\"header_separator rigth_separator\">&nbsp;</td>\n";
            }
            if($shownoanswer)
            {
                //$mycolumns .=  "\t<col class=\"col-no-answer\"  width=\"$cellwidth%\" />\n";
                $mycolumns .=  "\t<col class=\"col-no-answer\"  style='width: $cellwidth%;'/>\n";
                $answer_head_line .= "\n\t<th class=\"header_no_answer\">".gT('No answer')."</th>\n";
            }
            $answer_head2 = "\n<tr class=\"array1 header_row dontread\">\n"
            . $answer_head_line
            . "</tr>\n";
            // build first row of header if needed
            if ($leftheader != '' || $rightheader !='')
            {
                $answer_head1 = "<tr class=\"array1 groups header_row\">\n"
                . "\t<th class=\"header_answer_text\">&nbsp;</th>\n"
                . "\t<th colspan=\"".count($labelans0)."\" class=\"dsheader\">$leftheader</th>\n";
                if (count($labelans1)>0)
                {
                    $answer_head1 .= "\t<td class=\"header_separator\">&nbsp;</td>\n" // Separator
                    ."\t<th colspan=\"".count($labelans1)."\" class=\"dsheader\">$rightheader</th>\n";
                }
                if($shownoanswer || $rightexists)
                {
                    $rigthclass=($rightexists)?" header_answer_text_right":"";
                    $answer_head1 .= "\t<td class=\"header_separator {$rigthclass}\">&nbsp;</td>\n";
                    if($shownoanswer)
                    {
                        $answer_head1 .= "\t<th class=\"header_no_answer\">&nbsp;</th>\n";
                    }
                }
                $answer_head1 .= "</tr>\n";
            }
            else
            {
                $answer_head1 = "";
            }
            $answer .= '<div class="no-more-tables no-more-tables-array-dual">';
            //$answer .= "\n<table class=\"table-in-qanda-9 question subquestions-list questions-list\" summary=\"{$caption}\">\n"
            $answer .= "\n<table class=\"no-more-tables table-in-qanda-9 table question subquestion-list questions-list\">\n"
            . $mycolumns
            . "\n\t<thead>\n"
            . $answer_head1
            . $answer_head2
            . "\n\t</thead>\n"
            . "<tbody>\n";

            // And no each line of body
            $trbc = '';
            foreach ($aSubQuestions as $ansrow)
            {
                // Build repeat headings if needed
                if (isset($repeatheadings) && $repeatheadings > 0 && ($fn-1) > 0 && ($fn-1) % $repeatheadings == 0)
                {
                    if ( ($anscount - $fn + 1) >= $minrepeatheadings )
                    {
                        $answer .= "</tbody>\n<tbody>";// Close actual body and open another one
                        //$answer .= $answer_head1;
                        $answer .= "\n<tr class=\"hidden-xs repeat headings\">\n"
                        . $answer_head_line
                        . "</tr>\n";
                    }
                }
                $trbc = alternation($trbc , 'row');
                $answertext=$ansrow['question'];

                // rigth and center answertext: not explode for ? Why not
                if(strpos($answertext,'|'))
                {
                    $answertextrigth=substr($answertext,strpos($answertext,'|')+1);
                    $answertext=substr($answertext,0, strpos($answertext,'|'));
                }
                else
                {
                    $answertextrigth="";
                }
                if($centerexists)
                {
                    $answertextcenter=substr($answertextrigth,0, strpos($answertextrigth,'|'));
                    $answertextrigth=substr($answertextrigth,strpos($answertextrigth,'|')+1);
                }
                else
                {
                    $answertextcenter="";
                }

                $myfname= $ia[1].$ansrow['title'];
                $myfname0 = $ia[1].$ansrow['title'].'#0';
                $myfid0 = $ia[1].$ansrow['title'].'_0';
                $myfname1 = $ia[1].$ansrow['title'].'#1'; // new multi-scale-answer
                $myfid1 = $ia[1].$ansrow['title'].'_1';
                /* Check the Sub Q mandatory violation */
                if ($ia[6]=='Y' && (in_array($myfname0, $aMandatoryViolationSubQ) || in_array($myfname1, $aMandatoryViolationSubQ)))
                {
                    //$answertext = "<span class='errormandatory'>{$answertext}</span>";
                    $answertext ='
                                <div class="alert alert-danger" role="alert">'.
                                        $answertext
                                    .'
                                </div>';
                }
                // Get array_filter stuff
                list($htmltbody2, $hiddenfield)=return_array_filter_strings($ia, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, $trbc, $myfname,"tr","$trbc answers-list radio-list");
                $answer .= $htmltbody2;


                array_push($inputnames,$myfname0);
                $answer .= "\t<th class=\"answertext\">\n"
                . $hiddenfield
                . "$answertext\n";
                // Hidden answers used by EM: sure can be added in javascript
                $answer .= "<input type=\"hidden\" disabled=\"disabled\" name=\"java$myfid0\" id=\"java$myfid0\" value=\"";
                if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0])) {$answer .= $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0];}
                $answer .= "\" />\n";
                if (count($labelans1)>0) // if second label set is used
                {
                    $answer .= "<input type=\"hidden\" disabled=\"disabled\" name=\"java$myfid1\" id=\"java$myfid1\" value=\"";
                    if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1])) {$answer .= $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1];}
                    $answer .= "\" />\n";
                }
                $answer .= "\t</th>\n";
                $hiddenanswers='';
                $thiskey=0;
                $first = true;
                foreach ($labelcode0 as $ld)
                {
                    if ($first && $leftheader != '')
                    {
                        $answer .= "<td class='visible-xs'><em>$leftheader</em></td>";
                        $first = false;
                    }

                    $answer .= "\t<td data-title='" . $labelans0[$thiskey] . "' class=\"answer_cell_1_00$ld answer-item {$answertypeclass}-item\">\n"
                    . "\t<label for=\"answer{$myfid0}-{$ld}\"><input class=\"radio\" type=\"radio\" name=\"$myfname0\" value=\"$ld\" id=\"answer$myfid0-$ld\" ";
                    if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0] == $ld)
                    {
                        $answer .= CHECKED;
                    }
                    $answer .= "  />\n"
                    //. "<label class=\"hide read\" for=\"answer{$myfid0}-{$ld}\">$labelans0[$thiskey]</label>\n"
                    . "\n\t</label></td>\n";
                    $thiskey++;
                }
                if (count($labelans1)>0) // if second label set is used
                {
                    $answer .= "\t<td data-title='' class=\"dual_scale_separator information-item\">";
                    if ($shownoanswer)// No answer for accessibility and no javascript (but hide hide even with no js: need reworking)
                    {
                        $answer .= "\t<label for='answer$myfid0-'><input class='radio jshide read' type='radio' name='$myfname0' value='' id='answer$myfid0-' ";
                        if (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0] == "")
                        {
                            $answer .= CHECKED;
                        }
                        $answer .= " />\n";
                    }
                    //$answer .=  "<label for='answer$myfid0-' class= \"hide read\">".gT("No answer")."</label>";
                    $answer .= "\t{$answertextcenter}</label></td>\n"; // separator
                    array_push($inputnames,$myfname1);
                    $thiskey=0;
                    $first = true;
                    foreach ($labelcode1 as $ld) // second label set
                    {
                        if ($first && $rightheader != '')
                        {
                            $answer .= "<td class='visible-xs'><em>$rightheader</em></td>";
                            $first = false;
                        }

                        $answer .= "\t<td data-title='{$labelans1[$thiskey]}' class=\"answer_cell_2_00$ld  answer-item radio-item\">\n"
                        . "\t<label for=\"answer{$myfid1}-{$ld}\"><input class=\"radio\" type=\"radio\" name=\"$myfname1\" value=\"$ld\" id=\"answer$myfid1-$ld\" ";
                        if (isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1]) && $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1] == $ld)
                        {
                            $answer .= CHECKED;
                        }
                        $answer .= " />\n"
                        //. "<label class=\"hide read\" for=\"answer{$myfid1}-{$ld}\">{$labelans1[$thiskey]}</label>\n"
                        . "\t</label></td>\n";
                        $thiskey++;
                    }
                }
                if ($shownoanswer || $rightexists)
                {
                    $answer .= "\t<td class=\"answertextright dual_scale_separator information-item\">{$answertextrigth}</td>\n";
                }
                if ($shownoanswer)
                {
                    $answer .= "\t<td  data-title='".gT("No answer")."' class=\"dual_scale_no_answer answer-item radio-item noanswer-item\">\n";
                    if (count($labelans1)>0)
                    {
                        $answer .= "\t<label for='answer$myfid1-'><input class='radio' type='radio' name='$myfname1' value='' id='answer$myfid1-' ";
                        if (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1] == "")
                        {
                            $answer .= CHECKED;
                        }
                        // --> START NEW FEATURE - SAVE
                        $answer .= " />\n";
                        //$answer .= "<label class='hide read' for='answer$myfid1-'>".gT("No answer")."</label>";
                    }
                    else
                    {
                        $answer .= "\t<label for='answer$myfid0-'><input   data-title='".gT("No answer")."' class='radio' type='radio' name='$myfname0' value='' id='answer$myfid0-' ";
                        if (!isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0]) || $_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0] == "")
                        {
                            $answer .= CHECKED;
                        }
                        //$answer .= "<label class='hide read' for='answer$myfid0-'>".gT("No answer")."<label>\n";
                        $answer .= " />\n";
                    }
                    $answer .= "\t</label></td>\n";
                }
                $answer .= "</tr>\n";
                $fn++;
            }
            $answer.="</tbody>\n";
            $answer.="</table></div>";
        }


        // Dropdown Layout
        elseif($useDropdownLayout === true)
        {
            $separatorwidth=(100-$answerwidth)/10;
            $cellwidth=(100-$answerwidth-$separatorwidth)/2;

            $answer = "";

            // Get attributes for Headers and Prefix/Suffix
            if (trim($aQuestionAttributes['dropdown_prepostfix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']])!='') {
                list ($ddprefix, $ddsuffix) =explode("|",$aQuestionAttributes['dropdown_prepostfix'][$_SESSION['survey_'.Yii::app()->getConfig('surveyID')]['s_lang']]);
                $ddprefix = $ddprefix;
                $ddsuffix = $ddsuffix;
            }
            else
            {
                $ddprefix ='';
                $ddsuffix='';
            }
            if (trim($aQuestionAttributes['dropdown_separators'])!='') {
                $aSeparator =explode('|',$aQuestionAttributes['dropdown_separators']);
                if (isset($aSeparator[1])) {
                    $interddSep=$aSeparator[1];
                }
                else {
                    $interddSep=$aSeparator[0];
                }
            }
            else {
                $interddSep = '';
            }
            $colspan_1 = '';
            $colspan_2 = '';
            $suffix_cell = '';
            $answer .= '<div class="no-more-tables no-more-tables-array-dual-dropdown-layout">';
            //$answer .= "\n<table class=\"table-in-qanda-10 question subquestion-list questions-list dropdown-list\" summary=\"{$caption}\">\n"
            $answer .= "\n<table class=\"table-in-qanda-10 question subquestion-list questions-list dropdown-list\">\n"
            //. "\t<col class=\"answertext\" width=\"$answerwidth%\" />\n";
            . "\t<col class=\"answertext\" style='width: $answerwidth%;' />\n";

            if($ddprefix != '' || $ddsuffix != '')
            {
                //$answer .= "\t<colgroup width=\"$cellwidth%\">\n";
                $answer .= "\t<colgroup style='width: $cellwidth%;' >\n";
            }
            if($ddprefix != '')
            {
                $answer .= "\t\t<col class=\"ddprefix\" />\n";
                $colspan_1 = ' colspan="2"';
            }
            ////// TODO: check in prev headcolwidth if style='width:$cellwidth' and not style='width:\"$cellwidth\"'
            $headcolwidth=($ddprefix != '' || $ddsuffix != '')?"":" style='width:$cellwidth%';";
            //$headcolwidth="";
            $answer .= "\t<col class=\"dsheader\" {$headcolwidth} />\n";
            if($ddsuffix != '')
            {
                $answer .= "\t<col class=\"ddsuffix\" />\n";
            }
            if($ddprefix != '' || $ddsuffix != '')
            {
                $answer .= "\t</colgroup>\n";
            }
            //$answer .= "\t<col class=\"ddarrayseparator\" width=\"{$separatorwidth}%\" />\n";
            $answer .= "\t<col class=\"ddarrayseparator\" style='width: $separatorwidth%'/>\n";
            if($ddprefix != '' || $ddsuffix != '')
            {
                //$answer .= "\t<colgroup width=\"$cellwidth%\">\n";
                $answer .= "\t<colgroup style='width: $cellwidth%;' >\n";
            }
            if($ddprefix != '')
            {
                $answer .= "\t\t<col class=\"ddprefix\" />\n";
            }
            $answer .= "\t<col class=\"dsheader\"{$headcolwidth} />\n";
            if($ddsuffix != '')
            {
                $answer .= "\t<col class=\"ddsuffix\" />\n";
            }
            if($ddprefix != '' || $ddsuffix != '')
            {
                $answer .= "\t</colgroup>\n";
            }
            // colspan : for header only
            if($ddprefix != '' && $ddsuffix != '')
                $colspan=' colspan="3"';
            elseif($ddprefix != '' || $ddsuffix != '')
                $colspan=' colspan="2"';
            else
                $colspan="";
            // headers
            $answer .= "\n\t<thead>\n"
            . "<tr>\n"
            . "\t<td>&nbsp;</td>\n"
            . "\t<th  class='th-14' {$colspan}>$leftheader</th>\n"
            . "\t<td>&nbsp;</td>\n"
            . "\t<th class='th-15' {$colspan}>$rightheader</th>\n";
            $answer .="\t</tr>\n"
            . "\t</thead>\n";
            $answer .= "\n<tbody>\n";
            $trbc = '';
            foreach ($aSubQuestions as $ansrow)
            {

                $myfname = $ia[1].$ansrow['title'];
                $myfname0 = $ia[1].$ansrow['title']."#0";
                $myfid0 = $ia[1].$ansrow['title']."_0";
                $myfname1 = $ia[1].$ansrow['title']."#1";
                $myfid1 = $ia[1].$ansrow['title']."_1";
                $sActualAnswer0=isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0])?$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname0]:"";
                $sActualAnswer1=isset($_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1])?$_SESSION['survey_'.Yii::app()->getConfig('surveyID')][$myfname1]:"";
                if ($ia[6]=='Y' && (in_array($myfname0, $aMandatoryViolationSubQ) || in_array($myfname1, $aMandatoryViolationSubQ)))
                {
                    //$answertext="<span class='errormandatory'>".$ansrow['question']."</span>";
                    $answertext ='
                                <div class="alert alert-danger" role="alert">'.
                                        $ansrow['question']
                                    .'
                                </div>';
                }
                else
                {
                    $answertext=$ansrow['question'];
                }
                list($htmltbody2, $hiddenfield)=return_array_filter_strings($ia, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, $trbc, $myfname,"tr","$trbc subquestion-list questions-list dropdown-list");
                $answer .= $htmltbody2;
                $answer .= "\t<th class=\"answertext\">\n"
                . "<label for=\"answer$myfid0\">{$answertext}</label>\n";
                // Hidden answers used by EM: sure can be added in javascript
                $answer .= "<input type=\"hidden\" disabled=\"disabled\" name=\"java$myfid0\" id=\"java$myfid0\" value=\"{$sActualAnswer0}\" />\n";
                $answer .= "<input type=\"hidden\" disabled=\"disabled\" name=\"java$myfid1\" id=\"java$myfid1\" value=\"{$sActualAnswer1}\" />\n";
                $answer . "\t</th>\n";
                // Selector 0
                if($ddprefix != '')
                {
                    $answer .= "\t<td class=\"ddprefix information-item\">$ddprefix</td>\n";
                }
                $answer .= "\t<td class=\"answer-item dropdown-item\">\n"
                . "<select class='form-control' name=\"$myfname0\" id=\"answer$myfid0\">\n";

                // Show the 'Please choose' if there are no answer actually
                if ($sActualAnswer0 == '')
                {
                    $answer .= "\t<option value=\"\" ".SELECTED.">".gT('Please choose...')."</option>\n";
                }
                foreach ($labels0 as $lrow)
                {
                    $answer .= "\t<option value=\"".$lrow['code'].'" ';
                    if ($sActualAnswer0 == $lrow['code'])
                    {
                        $answer .= SELECTED;
                    }
                    $answer .= '>'.flattenText($lrow['title'])."</option>\n";
                }
                if ($sActualAnswer0 != '' && $ia[6] != 'Y' && SHOW_NO_ANSWER)
                {
                    $answer .= "\t<option value=\"\">".gT('No answer')."</option>\n";
                }
                $answer .= "</select>\n";
                $answer .= "</td>\n";
                if($ddsuffix != '')
                {
                    $answer .= "\t<td class=\"ddsuffix information-item\">$ddsuffix</td>\n";
                }
                $inputnames[]=$myfname0;

                $answer .= "\t<td class=\"ddarrayseparator information-item\">$interddSep</td>\n"; //Separator

                // Selector 1
                if($ddprefix != '')
                {
                    $answer .= "\t<td class='ddprefix information-item'>$ddprefix</td>\n";
                }
                $answer .= "\t<td class=\"answer-item dropdown-item\">\n"
                . "<label class=\"hide read\" for=\"answer{$myfid1}\">{$answertext}</label>"
                . "<select class='form-control' name=\"$myfname1\" id=\"answer$myfid1\">\n";
                // Show the 'Please choose' if there are no answer actually
                if ($sActualAnswer1 == '')
                {
                    $answer .= "\t<option value=\"\" ".SELECTED.">".gT('Please choose...')."</option>\n";
                }
                foreach ($labels1 as $lrow1)
                {
                    $answer .= "\t<option value=\"".$lrow1['code'].'" ';
                    if ($sActualAnswer1 == $lrow1['code'])
                    {
                        $answer .= SELECTED;
                    }
                    $answer .= '>'.flattenText($lrow1['title'])."</option>\n";
                }
                if ($sActualAnswer1 != '' && $ia[6] != 'Y' && SHOW_NO_ANSWER)
                {
                    $answer .= "\t<option value=\"\">".gT('No answer')."</option>\n";
                }
                $answer .= "</select>\n";
                $answer .= "</td>\n";
                if($ddsuffix != '')
                {
                    $answer .= "\t<td class=\"ddsuffix information-item\">$ddsuffix</td>\n";
                }
                $inputnames[]=$myfname1;

                $answer .= "</tr>\n";
            }
            $answer .= "\t</tbody>\n";
            $answer .= "</table>\n</div>";
        }
    }
    else
    {
        $answer = "<p class='error'>".gT("Error: There are no answer options for this question and/or they don't exist in this language.")."</p>\n";
        $inputnames="";
    }
    Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."dualscale.js");
    $answer .= "<script type='text/javascript'>\n"
    . "  <!--\n"
    ." {$doDualScaleFunction}({$ia[0]});\n"
    ." -->\n"
    ."</script>\n";
    return array($answer, $inputnames);
}

/**
 * Depending on prefix and suffix, the center col will vary
 * on sm screens (xs is always 12).
 *
 * @param string $prefix
 * @param string $suffix
 * @return int
 */
function decide_sm_col($prefix, $suffix)
{
    if ($prefix !== '' && $suffix !== '')
    {
        // Each prefix/suffix has 2 col space
        return 8;
    }
    elseif ($prefix !== '' || $suffix !=='')
    {
        return 10;
    }
    else
    {
        return 12;
    }

}
