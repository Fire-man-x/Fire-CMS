<?php
declare(strict_types=1);

define("VALIDATE_REQUIRED", "'%label' is required field.");
define("VALIDATE_FORMAT", "'%label' is not in correct format.");
define("VALIDATE_EXIST", "Record for value '%value' in field '%label' already exist.");

define("FLASH_SUCCESS", "success");
define("FLASH_FAILED", "warning");

define("PROMPT_VALUE", "-");

define("SUCCESS_SAVE", "Successfully saved");
define("FAIL_SAVE", "An error occurred when saving");
define("SUCCESS_DELETE", "Successfully deleted");
define("FAIL_DELETE", "An error occurred when deleting");

define("PERMISSION_FAIL", "You do not have permission to access the module.");

define("DATEPICKER_CLASS", "datePicker");
define("DATETIMEPICKER_CLASS", "datetimePicker");
define("DATE_FORMAT", "j.n.Y");
define("DATE_FORMAT_ZERO", "d.m.Y");
define("DATE_FORMAT_BROWSER", "Y-m-d");
define("TIME_FORMAT", "H:i");
define("DATETIME_FORMAT", "d.m.Y H:i");
define("DATETIME_WITH_SECONDS_FORMAT", "d.m.Y H:i:s");
define("DATE_REGEXP", "(0?[1-9]|[12][0-9]|3[01]){1}\.{1}(0?[1-9]|1[0-2]){1}\.{1}((19|20)[0-9]{2}){1}");
define("TIME_REGEXP", "([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]");
define("DATETIME_REGEXP", "(0?[1-9]|[12][0-9]|3[01]){1}\.{1}(0?[1-9]|1[0-2]){1}\.{1}((19|20)[0-9]{2}){1} ([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]");
define("DATE_REGEXP_BROWSER", "((19|20)[0-9]{2}){1}-(0?[1-9]|1[0-2]){1}-(0?[1-9]|[12][0-9]|3[01]){1}");

define("ICON_ADD", "fa fa-plus");
define("ICON_EDIT", "pencil-alt");
define("ICON_ITEMS", "list");
define("ICON_DUPLICATE", "files-alt");
define("ICON_DELETE", "trash");

define("WYSIWYG_CLASS", "summernote");
define("TYPEAHEAD_CLASS", "typeahead");