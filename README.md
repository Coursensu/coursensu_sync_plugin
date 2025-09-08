## 📦 Moodle / Coursensu Sync Service API

This repository defines a set of custom Moodle web service functions to automate the creation, updating, movement, and content population of course modules via REST API.

# 🔧 Built on top of local_sync_service.
🧠 Extended by Coursensu to support AI-powered course design workflows.

# 🚀 Available Functions

## 📁 Course Section & Module Management
### Function	Description
local_course_add_new_section	                              Add a new section to a course

local_course_get_sections	                                  Get all sections in a course

local_course_update_sections	                              Update a section in a course

local_course_move_module_to_specific_position	              Move a module to a specific position within a course

## 🔗 URL & Resources
### Function	Description
local_course_add_new_course_module_url	                    Add a URL resource

local_course_add_new_course_module_resource	                Add a file resource

local_course_update_course_module_resource	                Update a file resource

## 📄 Pages, Labels, and Content
### Function	Description
local_course_add_new_course_module_page	                      Add a Page module

local_course_update_course_module_page	Update a Page module

local_course_add_new_course_module_label	Add a Label (Text & Media)

local_course_update_course_module_label	Update a Label module

## 📂 Folder Management
### Function	Description
local_course_add_new_course_module_directory	Add a Folder module

local_course_add_files_to_directory	Upload files into a folder

## 📚 Book Module
### Function	Description
local_course_add_new_course_module_book	Add a Book module

local_course_import_html_in_book	Import HTML chapters into a Book

local_course_delete_all_chapters_from_book	Delete all chapters in a Book

## 💬 Forums, Assignments, Quizzes
### Function	Description
local_course_add_new_course_module_forum	Add a Forum

local_course_add_new_course_module_assignment	Add an Assignment

local_course_add_new_course_module_quiz	Add a Quiz

local_course_update_course_module_assignment	Update an Assignment

## 📘 Lessons
### Function	Description
local_course_update_course_module_lesson	Update a Lesson

local_course_update_course_module_lesson_contentpage	Update content page inside a Lesson

## 📎 Attachment Uploads
### Function	Description
local_course_save_attachment_in_assignment	Upload attachment to an Assignment

local_course_save_attachment_in_label	Upload attachment to a Label

local_course_save_attachment_in_page	Upload attachment to a Page

local_course_save_attachment_in_lesson	Upload attachment to a Lesson

local_course_save_attachment_in_lessonpage	Upload attachment to a Lesson page

## 🔗 Core Moodle Functions Included
### Function	Description
core_course_get_courses	List all courses

core_course_get_contents	Get full course structure and content

core_course_get_course_module	Retrieve details about a single module

core_enrol_get_users_courses	Get all courses a user is enrolled in

core_webservice_get_site_info	Get general site info (user, version, etc.)

core_course_delete_modules	Delete course modules

core_course_get_user_administration_options	Retrieve admin options per user/course

# 🛡️ Requirements
Moodle 3.11+ (Tested with 4.2 and 5.0.1)

REST enabled in Site Admin > Plugins > Web Services

Proper token permissions matching the capabilities listed per function

Your local/sync_service/externallib.php must define the logic for each method

# 🔐 Capabilities
Ensure your user/token has the required capabilities, such as:

mod/resource:addinstance

mod/page:addinstance

mod/assign:addinstance

moodle/course:movesections

...and others, depending on the module being used.

# 📦 Enabling the Service
Enable the service in Moodle:

Navigate to Site administration > Server > Web services > External services

Add a new service: sync_service

Add the functions listed in the $services array.

Generate a token for your user via Site Admin > Web services > Manage tokens

# 💬 Contact
Maintained by Coursensu

Forked and extended from Daniel Schröter
