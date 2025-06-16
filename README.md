# Feedora - Social Food Recipe Platform

Feedora is a web-based social platform designed for food enthusiasts to discover, create, and share recipes. It provides a space for users to connect with others, post their culinary creations, and explore a wide range of recipes from different categories and difficulty levels.

## Features

- **User Authentication**: Secure user registration and login functionality.
- **Recipe Management**: Create, view, and filter recipes based on categories and difficulty levels.
- **Social Posts**: Share posts with images, videos, and descriptions.
- **User Profiles**: View and manage user profiles with profile pictures and banners.
- **Stories**: Share temporary updates with stories that expire after 24 hours.
- **Real-Time Messaging**: A complete chat system with one-on-one conversations, media sharing, message reactions, and user presence (online/last seen).
- **Notifications**: A dynamic notification system that alerts users to new posts, stories, and other activities.
- **Live Cooking Sessions**: A placeholder for a future feature that will allow users to host and join live cooking streams.
- **File Uploads**: Upload images and videos with size limits and video compression.

## Technologies Used

- **Backend**: PHP
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript

## File Structure

Here is a breakdown of the key files and directories in the Feedora project:

### Core Application Files

- `Home.php`: The main landing page for both authenticated and guest users. It showcases featured content like recipes, community posts, and live sessions.
- `dashboard.php`: The central hub for logged-in users. It displays a feed of posts from other users, provides functionality for creating new posts, and shows user stories. It handles post editing/deletion and story cleanup.
- `profile.php`: Handles the initial profile setup for new users, prompting them to upload a profile picture.
- `profile_details.php`: Displays a user's public profile, including their posts, recipes, and basic information.
- `profile_settings.php`: Allows users to manage their profile information, including their name, bio, and profile/banner images.
- `settings.php`: Provides account management options, including updating profile information, changing passwords, and deleting the account.

### User Authentication

- `sign-up.php`: The user registration page. Handles new user creation.
- `sign-in.php`: The user login page. Manages user authentication and session creation.
- `log-out.php`: Terminates the user session, logging them out of the application.

### Content and Feature Management

- `recipes.php`: Manages the creation and display of recipes. Users can filter recipes by category and difficulty.
- `view-recipe.php`: Displays the detailed view of a single recipe, including ingredients and instructions.
- `post.php`: Handles the creation of new user posts, including text and media uploads.
- `story.php`: Manages the creation and viewing of stories. It includes logic for recording story views and sending notifications.
- `chat.php`: Powers the real-time messaging system. It handles sending/receiving messages, fetching conversations, and managing user presence.
- `get_user_list.php`: An API endpoint used by the chat feature to search for users to start a new conversation.
- `comments.php`: Handles the logic for adding, viewing, and reacting to comments on posts.
- `post_reaction.php`: Manages reactions (e.g., yummy, delicious) on posts.

### Notifications

- `notifications.php`: Displays a list of notifications for the logged-in user.
- `notification_handler.php`: (Note: This seems to be deprecated or replaced by the `_utils` and `_helpers` files).
- `notification_utils.php`: Contains utility functions for creating, fetching, and managing notifications for various events (new posts, stories, etc.).
- `notification_helpers.php`: Provides helper functions to support the notification system.

### Backend and Configuration

- `config/config.php`: Contains the database connection settings (PDO).
- `includes/`: A directory for reusable PHP scripts and components.
    - `story_cleanup.php`: A script to remove expired stories from the database.
    - `StoryRepository.php`, `StoryService.php`, etc.: Classes for handling story-related logic, following a more structured, object-oriented approach.
- `api/`: Directory for API endpoints.
- `cleanup.php`: A utility script for managing storage by cleaning up old files in the `uploads` directory.
- `Feedora.sql`: The database schema file. Contains the SQL statements to create all the necessary tables.

### Frontend and Assets

- `Home.css`, `messages.css`, `fonts.css`: CSS files for styling the application.
- `Home.js`: JavaScript file for handling frontend interactivity on the home page and other parts of the application.
- `uploads/`: The directory where all user-uploaded media (posts, profiles, stories) is stored.
- `images/`: Contains static images used in the site's design.
- `fonts/`: Contains font files.

## Database Schema

The database schema is defined in `Feedora.sql`. It includes tables for managing users, posts, recipes, stories, messages, notifications, and their relationships.

Key tables include:
- `USERS`: Stores user information.
- `POSTS`: Stores user-generated posts.
- `RECIPES`: Stores recipe details.
- `STORIES`: Handles temporary stories.
- `MESSAGES`: Stores chat messages.
- `NOTIFICATIONS`: Manages user notifications.

## Setup and Installation

1.  **Server Environment**:
    - Set up a local server environment like XAMPP or WAMP with PHP and MySQL.

2.  **Database Setup**:
    - Create a new MySQL database (e.g., `feedora`).
    - Import the `Feedora.sql` file into your database to create the required tables.

3.  **Configuration**:
    - Open `config/config.php` and update the database credentials (`$db_host`, `$db_name`, `$db_user`, `$db_pass`) to match your local environment.

4.  **Dependencies**:
    - For video compression on posts, ensure `ffmpeg` is installed on your server and that its path is accessible by the system.

5.  **Running the Application**:
    - Place the project files in your web server's root directory (e.g., `htdocs` for XAMPP).
    - Access the application through your web browser (e.g., `http://localhost/Feedora-Frontend`).
