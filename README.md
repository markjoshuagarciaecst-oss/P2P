# SkillSwap - Peer-to-Peer Skill Sharing Platform

A full-stack PHP-based skill-sharing platform where users exchange knowledge using a points-based barter system.

## Features

- **User Authentication**: Registration, login, profile management
- **Skill Listings**: Create, browse, and search skills by category and level
- **Booking System**: Request, accept/reject, and schedule sessions
- **Points System**: Earn points by teaching, spend points to learn
- **Reviews & Ratings**: Rate and review after each session
- **Dashboard**: Overview of points, sessions, and skills
- **Notifications**: Real-time alerts for booking requests and updates
- **Admin Panel**: Manage users, skills, and system activity

## Tech Stack

- **Backend**: PHP (Core PHP with OOP)
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript, Bootstrap 5
- **Icons**: Font Awesome 6

## Installation

### 1. Database Setup

1. Open phpMyAdmin or MySQL Workbench
2. Create a new database named `skillswap`
3. Import the `database.sql` file

```sql
CREATE DATABASE skillswap;
```

### 2. Configure Database

Edit `config/database.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'skillswap');
define('DB_USER', 'root');      // Your MySQL username
define('DB_PASS', '');          // Your MySQL password
```

### 3. Start Server

Using XAMPP:
1. Start Apache and MySQL services
2. Place the project in `htdocs/P2P`
3. Access `http://localhost/P2P`

## Project Structure

```
P2P/
├── admin/              # Admin panel pages
├── api/                # API endpoints
├── assets/
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   └── images/        # Images and assets
├── classes/           # PHP classes (User, Skill, Booking, etc.)
├── config/            # Configuration files
├── includes/          # Header and footer templates
├── pages/             # User-facing pages
├── database.sql       # Database schema
└── index.php          # Home page
```

## Default Points

- New users receive **100 points** upon registration
- Teaching a session earns points equal to the skill's `points_required`
- Learning a session deducts points from the learner's balance

## Demo Account

After importing the database, you can test with:
- **Email**: user@demo.com
- **Password**: demo123

Or register a new account to start fresh.

## Key Pages

| Page | URL | Description |
|------|-----|-------------|
| Home | `index.php` | Landing page with featured skills |
| Browse Skills | `pages/skills.php` | Search and filter skills |
| Login | `pages/login.php` | User login |
| Register | `pages/register.php` | User registration |
| Dashboard | `pages/dashboard.php` | User dashboard |
| My Skills | `pages/my-skills.php` | Manage skill listings |
| Bookings | `pages/bookings.php` | View and manage bookings |
| Profile | `pages/profile.php` | User profile |
| Admin | `admin/index.php` | Admin dashboard |

## License

MIT License