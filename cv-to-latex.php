<?php
/*
    CCCVTK, the Canadian Common CV Toolkit
    Copyright (C) 2013-2014 Sylvain Hallé

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * This file shows an example of usage of the CommonCV class to
 * query its data and produce a plain CV in LaTeX. The script outputs
 * the LaTeX source to the standard output. Redirect it to a file to then
 * compile it with pdflatex.
 */

// Include the CommonCV class
require_once("common-cv.lib.php");

// Include the Bibtex class
if (file_exists("bibtex-bib.lib.php"))
{
  require_once("bibtex-bib.lib.php");
  define("BIBTEX_PRESENT", true);
}
else
{
  define("BIBTEX_PRESENT", false);
  $bib = null;
}

/* Basic configuration. Fill in the blanks. */

// Location of the CV data
$cv_filename = "cv.xml";

// Location of the complementary BibTeX data
$bibtex_filename = "publications.bib";

// Your name; will be put in bold in the list of authors
$my_name = "S. Hallé";

// Word that will be used to separate the last name of a list from the
// next-to-last (typically " et " or " and ")
$and_word = " and ";

// Earliest year to include in various lists. You must put a *negative*
// number to include them all (and not a year long in the past like 1900)
$pub_first_year = -1;        // Publications
$funds_first_year = -1;      // Funds
$students_first_year = -1;   // Students
$courses_first_year = -1;    // Courses taught
$committee_first_year = -1;  // Committee memberships

// Instantiate a Common CV
$ccv = new CommonCV($cv_filename);
$stats = array();

// Instantiate a BibTeX object
if (BIBTEX_PRESENT)
{
  $bib = new Bibliography($bibtex_filename);
}

// Gather data from all sections
$s_date = date("Y-m-d");
$s_personal = section_personal_info($ccv);
$s_publications = section_publications($ccv, $pub_first_year);
$s_funding = section_funding($ccv, $funds_first_year);
$s_courses = section_courses($ccv, $courses_first_year);
$s_students = section_students($ccv, $students_first_year);
$s_reviews = section_reviewed_papers($ccv);
$s_committees = section_committees($ccv, $committee_first_year);
$s_summary = section_summary($stats); // Must be called last

// Fill in LaTeX document
echo <<<EOD
\\documentclass[11pt,letterpaper]{article}
\\usepackage[utf8]{inputenc}
\\usepackage[T1]{fontenc}
\\usepackage[english]{babel}
\\usepackage{enumerate}
\\usepackage{mathptmx}
\\usepackage{microtype}
\\usepackage[margin=1in]{geometry}
\\usepackage[bookmarks=true]{hyperref}
\\hypersetup{%
  pdfauthor = {{$my_name}},
  pdftitle = {Curriculum Vitæ}
}
\\begin{document}

\\title{Curriculum Vit\\ae{}}
\\author{}
\\date{}

\\maketitle

$s_personal

\\noindent
\\rule{6.5in}{0.5pt}
\\\\

$s_summary
$s_publications
$s_funding
$s_courses
$s_students
$s_reviews
$s_committees

\\noindent
Generated by CCCVTK on $s_date

\end{document}
EOD;

/* ------------------
 * Personal info
 * ------------------ */
function section_personal_info($ccv) // {{{
{
  global $stats;
  $tmp_out = "";
  $pinfo = $ccv->getPersonalInfo();
  $tmp_out .= "\\noindent\n";
  $tmp_out .= p($pinfo["first_name"])." ".p($pinfo["last_name"])."\\\\\n";
  foreach ($pinfo["addresses"] as $ad)
  {
    if ($ad["type"] !== $ccv->constants["Address Type"]["Primary Affiliation"])
      continue;
    $tmp_out .= (empty($ad["line1"]) ? "" : p($ad["line1"])."\\\\\n");
    $tmp_out .= (empty($ad["line2"]) ? "" : p($ad["line2"])."\\\\\n");
    $tmp_out .= (empty($ad["line3"]) ? "" : p($ad["line3"])."\\\\\n");
    $tmp_out .= (empty($ad["line4"]) ? "" : p($ad["line4"])."\\\\\n");
    $tmp_out .= (empty($ad["line5"]) ? "" : p($ad["line5"])."\\\\\n");
    $tmp_out .= p($ad["city"])." ".p($ad["postal_code"])."\\\\\n";
    break; // We process only the first primary address
  }
  return $tmp_out;
} // }}}

/* ------------------
 * Publications
 * ------------------ */
function section_publications($ccv, $pub_first_year = -1) // {{{
{
  global $and_word, $my_name, $stats, $bib;
  $t_out = "";
  $tmp_out = "";
  if ($pub_first_year < 0)
    $tmp_out .= "\n\\section*{Publications}\n";
  else
    $tmp_out .= "\n\\section*{Selected Publications (since $pub_first_year)}\n";
  $stats['pub_count'] = 0;
  $stats['pub_count_chapters'] = 0;
  $stats['pub_count_journals'] = 0;
    // Book chapters
  $publis = $ccv->getBookChapters();
  $first = true;
  foreach ($publis as $id => $pub)
  {
    if ($pub['date_year'] < $pub_first_year)
      continue;
    if ($first)
    {
      $first = false;
      $tmp_out .= "\n\\subsection*{Book Chapters}\n\n";
      $tmp_out .= "\\begingroup\n\\renewcommand{\\section}[2]{}%\n";
      $tmp_out .= "\\begin{thebibliography}{99}\n\n";
      //$tmp_out .= "\\setcounter{enumi}{".$stats['pub_count']."}\n";
    }
    $stats['pub_count']++;
    $stats['pub_count_chapters']++;
    $tmp_out .= "\\bibitem[".$stats['pub_count']."]{".$id."} ";
    $auth = $ccv->reverseAuthors($pub['authors']);
    $editors = $ccv->reverseAuthors($pub['editors']);
    $tmp_out .= and_on_last(latex_periods(to_boldface($my_name, p($auth))), $and_word).p(". ({$pub['date_year']}). {$pub['title']}. In ");
    $n_ed = count_names($editors);
    if ($n_ed == 1)
      $tmp_out .= and_on_last(latex_periods(p($editors)), $and_word).", ed. ";
    if ($n_ed > 1)
      $tmp_out .= and_on_last(latex_periods(p($editors)), $and_word).", eds. ";
    if (!empty($pub['booktitle']))
      $tmp_out .= "\\textit{".p("{$pub['booktitle']}")."}, ";
    if (empty($pub['volume']) && !empty($pub['number']))
      $tmp_out .= p("{$pub['number']}, ");
    elseif (!empty($pub['volume']) && !empty($pub['number']))
      $tmp_out .= p("{$pub['volume']} ({$pub['number']}), ");
    $tmp_out .= p("{$pub['publisher']}");
    if (empty($pub['pages']))
      $tmp_out .= ".";
    else
      $tmp_out .= ", ".str_replace("-", "--", p("{$pub['pages']}.\n"));
    switch ($pub['status'])
    {
        case $ccv->constants['Publishing Status']['Accepted']:
          $tmp_out .= " Accepted for publication.";
          break;
        case $ccv->constants['Publishing Status']['Submitted']:
        case $ccv->constants['Publishing Status']['Revision Requested']:
          $tmp_out .= " Submitted.";
          break;
        case $ccv->constants['Publishing Status']['In Press']:
          $tmp_out .= " In press.";
          break;
        default:
          break;
    }
    if (!empty($pub['url']))
      $tmp_out .= " \\url{".$pub['url']."}";
    $tmp_out .= "\n\n";
  }
  if (!$first)
    $tmp_out .= "\\end{thebibliography}\n\\endgroup\n";
  
  // Journals
  $publis = $ccv->getJournalPapers();
  $first = true;
  foreach ($publis as $id => $pub)
  {
    if ($pub['date_year'] < $pub_first_year)
      continue;
    if ($first)
    {
      $first = false;
      $tmp_out .= "\n\\subsection*{Journal papers}\n\n";
      $tmp_out .= "\\begingroup\n\\renewcommand{\\section}[2]{}%\n";
      $tmp_out .= "\\begin{thebibliography}{99}\n\n";
      $tmp_out .= "\\setcounter{enumi}{".$stats['pub_count']."}\n";
    }
    $stats['pub_count']++;
    $stats['pub_count_journals']++;
    $tmp_out .= "\\bibitem[".$stats['pub_count']."]{".$id."} ";
    $auth = $ccv->reverseAuthors($pub['authors']);
    $editors = $ccv->reverseAuthors($pub['editors']);
    $tmp_out .= and_on_last(latex_periods(to_boldface($my_name, p($auth))), $and_word).p(". ({$pub['date_year']}). {$pub['title']}. In ");
    $n_ed = count_names($editors);
    if ($n_ed == 1)
      $tmp_out .= and_on_last(latex_periods(p($editors)), $and_word).", ed. ";
    if ($n_ed > 1)
      $tmp_out .= and_on_last(latex_periods(p($editors)), $and_word).", eds. ";
    if (!empty($pub['journal']))
      $tmp_out .= "\\textit{".p("{$pub['journal']}")."}, ";
    if (empty($pub['volume']) && !empty($pub['number']))
      $tmp_out .= p("{$pub['number']}, ");
    elseif (!empty($pub['volume']) && !empty($pub['number']))
      $tmp_out .= p("{$pub['volume']} ({$pub['number']}), ");
    $tmp_out .= p("{$pub['publisher']}");
    if (empty($pub['pages']))
      $tmp_out .= ".";
    else
      $tmp_out .= ", ".str_replace("-", "--", p("{$pub['pages']}.\n"));
    switch ($pub['status'])
    {
        case $ccv->constants['Publishing Status']['Accepted']:
          $tmp_out .= " Accepted for publication.";
          break;
        case $ccv->constants['Publishing Status']['Submitted']:
        case $ccv->constants['Publishing Status']['Revision Requested']:
          $tmp_out .= " Submitted.";
          break;
        case $ccv->constants['Publishing Status']['In Press']:
          $tmp_out .= " In press.";
          break;
        default:
          break;
    }
    // Complement with BibTeX data if any
    if (BIBTEX_PRESENT)
    {
      $bib_entry = $bib->getEntryByTitle($pub['title']);
      if ($bib_entry != null)
      {
	if (isset($bib_entry["impactfactor"]))
	{
	  list($year, $ifactor) = explode(":", $bib_entry["impactfactor"]);
	  $tmp_out .= " \\textsl{Impact factor: $ifactor in $year.} ";
	}
      }
    }
    if (!empty($pub['url']))
      $tmp_out .= " \\url{".$pub['url']."}";
    $tmp_out .= "\n\n";
  }
  if (!$first)
    $tmp_out .= "\\end{thebibliography}\n\\endgroup\n";
  $t_out .= $tmp_out;

  // Conferences/workshops
  $publis = $ccv->getConferencePapers();
  $first = true;
  $stats['pub_count_confs'] = 0;
  foreach ($publis as $id => $pub)
  {
    if ($pub['date_year'] < $pub_first_year)
      continue;
    if ($first)
    {
      $first = false;
      $t_out .= "\n\\subsection*{Peer-reviewed conference papers}\n\n";
      $t_out .= "\\begingroup\n\\renewcommand{\\section}[2]{}%\n";
      $t_out .= "\\begin{thebibliography}{99}\n\n";
      $t_out .= "\\setcounter{enumi}{".$stats['pub_count']."}\n";
    }
    $stats['pub_count']++;
    $stats['pub_count_confs']++;
    $t_out .= "\\bibitem[".$stats['pub_count']."]{".$id."} ";
    $auth = $ccv->reverseAuthors($pub['authors']);
    $editors = $ccv->reverseAuthors($pub['editors']);
    $t_out .= and_on_last(latex_periods(to_boldface($my_name, p($auth))), $and_word).p(". ({$pub['date_year']}). {$pub['title']}. In ");
    $n_ed = count_names($editors);
    if ($n_ed == 1)
      $t_out .= and_on_last(latex_periods(p($editors)), $and_word).", ed. ";
    if ($n_ed > 1)
      $t_out .= and_on_last(latex_periods(p($editors)), $and_word).", eds. ";
    if (!empty($pub['conf_name']))
      $t_out .= p("{$pub['conf_name']}");
    if (!empty($pub['publisher']))
      $t_out .= ", ".p("{$pub['publisher']}");
    if (!empty($pub['published_in']))
      $t_out .= ", ".p("{$pub['published_in']}. ");
    else
      $t_out .= ". ";
    $t_out .= str_replace("-", "--", p("{$pub['pages']}.\n"));
    switch ($pub['status'])
    {
        case $ccv->constants['Publishing Status']['Accepted']:
          $t_out .= " Accepted for publication.";
          break;
        case $ccv->constants['Publishing Status']['Submitted']:
        case $ccv->constants['Publishing Status']['Revision Requested']:
          $t_out .= " Submitted.";
          break;
        case $ccv->constants['Publishing Status']['In Press']:
          $t_out .= " In press.";
          break;
        default:
          break;
    }
    // Complement with BibTeX data if any
    if (BIBTEX_PRESENT)
    {
      $bib_entry = $bib->getEntryByTitle($pub['title']);
      if ($bib_entry != null)
      {
	if (isset($bib_entry["rate"]))
	{
	  $t_out .= " \\textsl{Acceptance rate: ".$bib_entry["rate"]."\\%} ";
	}
      }
    }
    if (!empty($pub['url']))
      $t_out .= " \\url{".$pub['url']."}";
    if (BIBTEX_PRESENT && isset($bib_entry["note"]) && strpos($bib_entry["note"], "award") !== false)
    {
      $t_out .= " \\\\ \\textbf{Best paper award} ";
    }
    $t_out .= "\n\n";
  }
  if (!$first)
    $t_out .= "\\end{thebibliography}\n\\endgroup\n";
  return $t_out;
} // }}}

/* ------------------
 * Courses
 * ------------------ */
function section_courses($ccv, $courses_first_year) // {{{
{
  global $ccv, $stats;
  $t_out = "";
  $courses = $ccv->getCoursesTaught();
  $first = true;
  $stats['num_courses_taught'] = 0;
  $course_codes = array();
  $stats['num_students_supervised'] = 0;
  $tmp_out = "";
  foreach ($courses as $id => $course)
  {
    // We keep only courses that started after the cutoff year
    if ($course['start_date_year'] < $courses_first_year)
      continue;
    if ($first)
    {
      $first = false;
      if ($courses_first_year < 0)
        $t_out .= "\n\\section*{Courses Taught}\n";
      else
        $t_out .= "\n\\section*{Courses Taught (since $courses_first_year)}\n";
      $tmp_out .= "\\begin{itemize}\n\n";
    }
    $tmp_out .= "\\item ";
    $tmp_out .= p($course["code"]." ".$course["title"])." ";
    $tmp_out .= "(".p(get_month($course["start_date_month"]));
    if ($course["start_date_month"] == $course["end_date_month"] &&
      $course["start_date_year"] == $course["end_date_year"])
    {
      $tmp_out .= " ".p($course["start_date_year"]);
    }
    else
    {
      $tmp_out .= p("--".get_month($course["end_date_month"])." ".$course["start_date_year"]);
    }
    $tmp_out .= ") ";
    $tmp_out .= ".\n\n";
    // Gather stats
    $stats['num_students_supervised'] += $course["nb_students"];
    $stats['num_courses_taught']++;
    if (!in_array($course["code"], $course_codes))
      $course_codes[] = $course["code"];
  }
  if (!$first)
    $tmp_out .= "\\end{itemize}\n";
  $t_out = "\nI taught \\textbf{".$stats['num_courses_taught']."} courses (".count($course_codes)." different) to a total of \\textbf{".number_format($stats['num_students_supervised'], 0, '.', ',')."} students.\n\n";
  $t_out .= $tmp_out;
  return $t_out;
} // }}}

/* ------------------
 * Money
 * ------------------ */
function section_funding($ccv, $funds_first_year) // {{{
{
  global $and_word, $stats;
  $t_out = "";
  $funds = $ccv->getFunding();
  $first = true;
  $tmp_out = "";
  $stats['total_amount'] = 0;
  $num_funds = 0;
  foreach ($funds as $id => $fund)
  {
    // We keep only funds that started after the cutoff year
    if ($fund['start_year'] < $funds_first_year)
      continue;
    if ($first)
    {
      $first = false;
      if ($funds_first_year < 0)
        $t_out .= "\n\\section*{Research Funding}\n";
      else
        $t_out .= "\n\\section*{Research Funding (since $funds_first_year)}\n";
      $tmp_out .= "\\begin{itemize}\n\n";
    }
    $tmp_out .= "\\item ";
    $tmp_out .= p($fund["start_year"]);
    if (!empty($fund['end_year']))
      if ($fund['end_year'] == $fund['start_year'])
        $tmp_out .= " ";
      else
        $tmp_out .= p("--{$fund['end_year']} ");
    else
    {
      $tmp_out .= "-in progress. ";
    }
    if ($fund['funder'] != "")
    {
      $fundername = p($ccv->getCaptionFromValue($fund['funder'], "Funding Organization"));
    }
    else
    {
      $fundername = $fund['otherfunder'];
    }
    $tmp_out .= "$\\cdot$ $fundername";
    if (!empty($fund['funding_program']))
      $tmp_out .= ", ".p($fund['funding_program']);
    $tmp_out .= " $\\cdot$ ".p($fund['funding_title']).". ";
    $tmp_out .= "(\\$".number_format($fund['total_amount'], 0, '.', ',').")\n";
    $num_co_holders = count($fund['co_holders']);
    if ($num_co_holders > 0)
    {
      $chc = 0;
      $tmp_out .= "In collaboration with ";
      foreach ($fund['co_holders'] as $ch)
      {
        if ($chc > 0)
        {
          if ($chc == $num_co_holders - 1)
            $tmp_out .= $and_word;
          else
            $tmp_out .= ", ";
        }
        $tmp_out .= $ccv->reverseAuthor($ch['name'], false);
        $chc++;
      }
      $tmp_out .= ".\n\n";
    }
    $num_funds++;
    $stats['total_amount'] += $fund['total_amount'];
  }
  if (!$first)
    $tmp_out .= "\\end{itemize}\n";
  $t_out .= "\nI received funds through \\textbf{".$num_funds."}  different applications and participated in research projects totaling \\textbf{\\$".number_format($stats['total_amount'], 0, '.', ',')."} in research grants.\n\n";
  $t_out .= $tmp_out;
  return $t_out;
} // }}}

/* ------------------
 * Students
 * ------------------ */
function section_students($ccv, $students_first_year) // {{{
{
  global $stats;
  $t_out = "";
  $first = true;
  $students = $ccv->getStudentsSupervised();
  $tmp_out = "";
  $stats['supervised_phd'] = 0;
  $stats['supervised_msc'] = 0;
  $stats['supervised_undergrad'] = 0;
  foreach ($students as $id => $stud)
  {
    if (!empty($stud['end_year']) && $stud['end_year'] < $students_first_year)
      continue;
    if ($first)
    {
      $first = false;
      if ($students_first_year < 0)
        $t_out .= "\n\\section*{Students supervised}\n";
      else
        $t_out .= "\n\\section*{Students supervised (since $students_first_year)}\n";
      $tmp_out .= "\\begin{enumerate}\n\n";
    }
    $tmp_out .= "\\item ";
    $auth = $ccv->reverseAuthor($stud['name'], false);
    $tmp_out .= p("$auth ({$stud['start_year']}");
    if (!empty($stud['end_year']))
      if ($stud['end_year'] == $stud['start_year'])
        $tmp_out .= "). ";
      else
        $tmp_out .= p("--{$stud['end_year']}). ");
    else
      $tmp_out .= "--in progress). ";
    if (!empty($stud['title']))
      $tmp_out .= "{$stud['title']}. ";
    switch ($stud['diploma'])
    {
      case $ccv->constants["Degree Type"]["Bachelor's"]:
      case $ccv->constants["Degree Type"]["Bachelor's Honours"]:
      case $ccv->constants["Degree Type"]["Bachelor's Equivalent"]:
        $tmp_out .= "B.Sc.";
        $stats['supervised_undergrad']++;
        break;
      case $ccv->constants["Degree Type"]["Master's Thesis"]:
      case $ccv->constants["Degree Type"]["Master's non-Thesis"]:
      case $ccv->constants["Degree Type"]["Master's Equivalent"]:
        $tmp_out .= "M.Sc.";
        $stats['supervised_msc']++;
        break;
      case $ccv->constants["Degree Type"]["Doctorate"]:
      case $ccv->constants["Degree Type"]["Doctorate Equivalent"]:
        $tmp_out .= "Ph.D.";
        $stats['supervised_phd']++;
        break;
    }
    $tmp_out .= "\n\n";
  }
  if (!$first)
    $tmp_out .= "\\end{enumerate}\n";
  $t_out .= "\nI supervised \\textbf{".$stats['supervised_phd']."} Ph.D.\\ student".($stats['supervised_phd'] > 1 ? "s" : "").", ";
  $t_out .= "\\textbf{".$stats['supervised_msc']."} M.Sc.\\ student".($stats['supervised_msc'] > 1 ? "s" : "")." and ";
  $t_out .= "\\textbf{".$stats['supervised_undergrad']."} undergraduate student".($stats['supervised_undergrad'] > 1 ? "s" : "").", ";
  $t_out .= "as listed below.\n\n";
  $t_out .= $tmp_out;
  return $t_out;
} // }}}

/* ------------------
 * Reviewed papers
 * ------------------ */
function section_reviewed_papers($ccv) // {{{
{
  global $stats;
  // Journals
  $first = true;
  $tmp_out = "";
  $papers = $ccv->getReviewedJournalPapers();
  $statts['num_reviewed_confs'] = 0; $statts['num_reviewed_journals'] = 0;
  $stats['num_reviewed_conf_papers'] = 0; $stats['num_reviewed_journal_papers'] = 0;
  foreach($papers as $paper)
  {
    if ($first)
    {
      $first = false;
      $tmp_out .= "\n\\subsection*{Journals}\n";
      $tmp_out .= "\\begin{itemize}\n\n";
    }
    $statts['num_reviewed_journals']++;
    $stats['num_reviewed_journal_papers'] += $paper['numpapers'];
    $tmp_out .= "\\item ";
    $tmp_out .= p($paper['journal']).", ".p($paper['publisher'])." (".$paper['numpapers'].")";
    $tmp_out .= "\n\n";
  }
  if (!$first)
    $tmp_out .= "\\end{itemize}\n";
  
  // Conferences
  $first = true;
  $papers = $ccv->getReviewedConferencePapers();
  foreach($papers as $paper)
  {
    if ($first)
    {
      $first = false;
      $tmp_out .= "\n\\subsection*{Conferences}\n";
      $tmp_out .= "\\begin{itemize}\n\n";
    }
    $statts['num_reviewed_confs']++;
    $stats['num_reviewed_conf_papers'] += $paper['numpapers'];
    $tmp_out .= "\\item ";
    $tmp_out .= p($paper['conference'])." (".$paper['numpapers'].")";
    $tmp_out .= "\n\n";
  }
  if (!$first)
    $tmp_out .= "\\end{itemize}\n";
  $t_out = "";
  if (!empty($tmp_out))
  {
    $t_out .= "\n\\section*{Reviewed Papers}\n\n";
    $t_out .= "I reviewed a total of ".$stats['num_reviewed_journal_papers']." paper".($stats['num_reviewed_journal_papers'] > 1 ? "s" : "")." for ".$statts['num_reviewed_journals']." journal".($statts['num_reviewed_journals'] > 1 ? "s" : "");
    $t_out .= " and ".$stats['num_reviewed_conf_papers']." paper".($stats['num_reviewed_conf_papers'] > 1 ? "s" : "")." for ".$statts['num_reviewed_confs']." conference".($statts['num_reviewed_confs'] > 1 ? "s" : "").". ";
    $t_out .= "In the following, the number of reviewed papers are shown in parentheses.\n\n";
    $t_out .= $tmp_out;
  }
  return $t_out;
} // }}}

/* ------------------
 * Committees
 * ------------------ */
function section_committees($ccv, $committee_first_year) // {{{
{
  global $stats;
  $t_out = "";
  $first = true;
  $tmp_out = "";
  $papers = $ccv->getCommittees();
  $stats['num_committees'] = 0;
  foreach($papers as $paper)
  {
    if (!empty($paper['end_year']) && $paper['end_year'] < $committee_first_year)
      continue;
    if ($first)
    {
      $first = false;
      if ($committee_first_year < 0)
        $t_out .= "\n\\section*{Committee Memberships}\n\n";
      else
        $t_out .= "\n\\section*{Committee Memberships (since $committee_first_year)}\n";
      $tmp_out .= "\\begin{itemize}\n\n";
    }
    $stats['num_committees']++;
    $tmp_out .= "\\item ";
    $tmp_out .= $paper['start_year'];
    if (!empty($paper['end_year']))
      if ($paper['end_year'] == $paper['start_year'])
        $tmp_out .= "";
      else
        $tmp_out .= p("--{$paper['end_year']}");
    else
      $tmp_out .= "--present";
    $tmp_out .= " \$\\cdot\$ ".p($paper['role']).", ".p($paper['name']);
    if (!empty($paper['organization_name']))
      $tmp_out .= ", ".p($paper['organization_name']);
    $tmp_out .= "\n\n";
  }
  if (!$first)
    $tmp_out .= "\\end{itemize}\n";
  if (empty($tmp_out))
    return "";
  else
  {
    $t_out .= $tmp_out;
    return $t_out;
  }
} // }}}

/* ------------------
 * Summary: just the numbers
 * ------------------ */
function section_summary($stats) // {{{
{
  global $stats;
  $t_out = "";
  $t_out .= "\\section*{Summary}\n\n";
  $t_out .= "\\begin{center}\n";
  $t_out .= "\\begin{tabular}{|l|c|}\n";
  $t_out .= "\\hline\n";
  $t_out .= "Publications & ".$stats['pub_count']." \\\\\n";
  $t_out .= "\hspace{10pt} Book Chapters & ".$stats['pub_count_chapters']." \\\\\n";
  $t_out .= "\hspace{10pt} Journals & ".$stats['pub_count_journals']." \\\\\n";
  $t_out .= "\hspace{10pt} Conferences & ".$stats['pub_count_confs']." \\\\\n";
  $t_out .= "\\hline\n";
  $t_out .= "Total Research Funds & \\$".number_format($stats['total_amount'], 0, '.', ',')." \\\\\n";
  $t_out .= "\\hline\n";
  $t_out .= "Students Supervised & ".($stats['supervised_phd'] + $stats['supervised_msc'] + $stats['supervised_undergrad'])." \\\\\n";
  $t_out .= "\hspace{10pt} Ph.D. & ".$stats['supervised_phd']." \\\\\n";
  $t_out .= "\hspace{10pt} M.Sc. & ".$stats['supervised_msc']." \\\\\n";
  $t_out .= "\hspace{10pt} Undergraduate & ".$stats['supervised_undergrad']." \\\\\n";
  $t_out .= "\\hline\n";
  $t_out .= "Courses Taught & ".($stats['num_courses_taught'])." \\\\\n";
  $t_out .= "Number of students & ".$stats['num_students_supervised']." \\\\\n";
  $t_out .= "\\hline\n";
  $t_out .= "Papers Reviewed & ".($stats['num_reviewed_journal_papers'] + $stats['num_reviewed_conf_papers'])." \\\\\n";
  $t_out .= "\hspace{10pt} Journals & ".$stats['num_reviewed_journal_papers']." \\\\\n";
  $t_out .= "\hspace{10pt} Conferences & ".$stats['num_reviewed_conf_papers']." \\\\\n";
  $t_out .= "\\hline\n";
  $t_out .= "Committee Memberships & ".$stats['num_committees']." \\\\\n";
  $t_out .= "\\hline\n";
  $t_out .= "\\end{tabular}\n";
  $t_out .= "\\end{center}\n";
  return $t_out;
} // }}}

/* Some useful functions */

/**
 * Replaces symbols in a string by their LaTeX equivalent. The function's
 * name is deliberately short, as it is called very often in the code above.
 * @param $s The string to escape
 * @return The escaped string
 */
function p($s) // {{{
{
  $out = $s;
  $out = str_replace("\\", "\\\\", $out);
  $out = str_replace("&", "\\&", $out);
  $out = str_replace("$", "\\$", $out);
  return $out;
} // }}}

/**
 * Looks for $x in $s, and surrounds it by a boldface command
 */
function to_boldface($x, $s) // {{{
{
  return str_replace($x, "\\textbf{".$x."}", $s);
} // }}}

/**
 * Counts the names in a string of the form
 * "First Last, First Last, ..."
 */
function count_names($s) // {{{
{
  if (empty($s))
    return 0;
  $n_matches = preg_match_all("/,/", $s, $matches);
  return $n_matches + 1;
} // }}}

/**
 * Replaces periods in name initials by backslashed periods so that
 * LaTeX does not interpret them as end-of-sentence markers.
 */
function latex_periods($s, $except_last = false) // {{{
{
  $s = trim($s);
  $out_s = preg_replace("/\\./", ".\\ ", $s);
  if ($except_last === true)
  {
    $out_s = substr($out_s, 0, strlen($out_s) - 2);
  }
  return $out_s;
} // }}}

/**
 * Replaces the last comma of a list of names by some word (typically
 * "and".
 */
function and_on_last($s, $word = " and ", $separator = ",") // {{{
{
  return str_lreplace($separator, $word, $s);
} // }}}

/**
 * Replace last occurrence of search by replace in subject
 */
function str_lreplace($search, $replace, $subject) // {{{
{
  $pos = strrpos($subject, $search);
  if($pos !== false)
  {
      $subject = substr_replace($subject, $replace, $pos, strlen($search));
  }
  return $subject;
} // }}}

/**
 * Get month name from number
 */
function get_month($m) // {{{
{
  $months = array("01" => "January", "02" => "February", "03" => "March",
    "04" => "April", "05" => "May", "06" => "June", "07" => "July", 
    "08" => "August", "09" => "September", "10" => "October", 
    "11" => "November", "12" => "December");
  return $months[$m];
  switch ($m)
  {
    case "01":
      return "January";
      break;
    case "02":
      return "February";
      break;
    case "03":
      return "March";
      break;
    case "04":
      return "April";
      break;
    case "05":
      return "May";
      break;
    case "06":
      return "June";
      break;
    case "07":
      return "July";
      break;
    case "08":
      return "August";
      break;
    case "09":
      return "September";
      break;
    case "10":
      return "October";
      break;
    case "11":
      return "November";
      break;
    case "12":
      return "December";
      break;
  }
  return "?";
} // }}}

/* :folding=explicit:wrap=none: */
?>
