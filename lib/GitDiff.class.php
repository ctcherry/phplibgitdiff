<?php


class GitDiffLine {
  
  function __construct($line, $lnum, $rnum, $mode) {
    $this->mode = $mode;
    $this->original_line = $line;
    $this->line_numbers = array('left' => $lnum, 'right' => $rnum);
    if ($s = substr($line, 1)) {
      $this->line = $s;
    } else {
      $this->line = '';
    }
  }
  
  public function __toString() {
    return $this->line;
  }
  
}


class GitDiffSection {
  
  private $header_pattern = "/@@ -(\d+),(\d+) \+(\d+),(\d+) @@(.*)/i";
  private $diff_lines;
  
  function __construct($section_lines) {
    $this->diff_lines = $section_lines;
    $this->header = $this->diff_lines[0];
    list ($this->left_line_offset, $this->left_line_count, $this->right_line_offset, $this->right_line_count) = $this->get_metrics();
    $this->lines = $this->get_lines();
  }
  
  private function get_metrics() {
    preg_match($this->header_pattern, $this->header, $matches);
    array_shift($matches);
    return $matches;
  }
  
  private function get_lines() {
    $lines = $this->diff_lines;
    // Remove the metrics line
    array_shift($lines);
    
    $obj_lines = array();
    
    $left_line_num = $this->left_line_offset;
    $right_line_num = $this->right_line_offset;
    
    foreach ($lines as $line) {
      
      $m = $this->line_mode($line);
      $l = null;
      $r = null;
      
      if ($m == 0) {
        $l = $left_line_num++;
        $r = $right_line_num++;
      }
      
      if ($m == -1) {
        $l = $left_line_num++;
      }
      
      if ($m == 1) {
        $r = $right_line_num++;
      }
      
      $ol = new GitDiffLine($line, $l, $r, $m);
      
      $obj_lines[] = $ol;
    }
    
    return $obj_lines;
  }
  
  private function line_mode($line) {
    $s = $line[0];
    // This line does not appear on the left side of the diff, but does appear on the right, line addition
    if ($s === '+') {
      return 1;
    }
    
    // This line appears on the left side of the diff, and not the right, line removal
    if ($s == '-') {
      return -1;
    }
    
    // This line appears on both sides of the diff no line change
    if ($s == ' ') {
      return 0;
    }
  }
  
}

class GitDiffFile {
  
  function __construct($file_lines) {
    $this->diff_lines = $file_lines;
    $this->header = $this->diff_lines[0];
    $this->file_name = $this->get_file_name();
    $this->meta = $this->diff_lines[1];
    $this->sections = $this->get_sections();
  }
  
  private function get_file_name() {
    preg_match("#.*b/(.*)$#i", $this->header, $matches);
    return $matches[1];
  }
  
  private function get_sections() {
    $capture = false;
    $buffer = array();
    $sections = array();
    foreach ($this->diff_lines as $line) {
      if (stripos($line, '@@') === 0) {
        $capture = true;
        if (!empty($buffer)) {
          $sections[] = $buffer;
          $buffer = array();
        }
      }
      if ($capture) {
        $buffer[] = $line;
      }
    }
    
    $sections[] = $buffer;
    $obj_sections = array();
    
    foreach($sections as $section) {
      $obj_sections[] = new GitDiffSection($section);
    }
    
    return $obj_sections;
    
  }
  
}

class GitDiff {
  
  private $raw_diff;
  
  function __construct($diffdata) {
    $this->raw_diff = $diffdata;
    $this->files = $this->get_files();
  }
  
  private function get_files() {
    $files = array();
    $buffer = array();
    
    foreach(preg_split("/(\r?\n)/", $this->raw_diff) as $line){
      if (stripos($line, 'diff --git') === 0) {
        if (!empty($buffer)) {
          $files[] = $buffer;
          $buffer = array();
        }
      }
      $buffer[] = $line;
    }
    
    $files[] = $buffer;
    $obj_files = array();
    
    foreach($files as $file) {
      $obj_files[] = new GitDiffFile($file);
    }
    
    return $obj_files;
  }
  
}