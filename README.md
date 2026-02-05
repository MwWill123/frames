# 🎬 FRAMES - Video Editor Platform

**Unlock Your Visual Story**

A modern, responsive web platform connecting video editors with clients. Built with HTML5, CSS3, JavaScript, and PHP.

---

## 📋 Table of Contents

- [Features](#features)
- [Technologies](#technologies)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Database Schema](#database-schema)
- [Contributing](#contributing)
- [License](#license)

---

## ✨ Features

### Frontend
- ⚡ **Responsive Design** - Optimized for desktop, tablet, and mobile devices
- 🎨 **Modern UI/UX** - Sleek design with smooth animations and transitions
- 🔍 **Advanced Search** - Real-time search with filters
- 🎯 **Format Categories** - Reels/TikTok, YouTube Vlog, Documentary, Commercial, Gameplay
- 🎪 **Interactive Carousel** - Browse featured editors with smooth navigation
- 📱 **Sidebar Filters** - Dynamic filtering by software, style, and categories
- 🌙 **Dark Theme** - Eye-friendly dark mode design
- ✨ **Parallax Effects** - Interactive mouse-based background animations
- 🔔 **Toast Notifications** - User-friendly feedback system

### Backend
- 🔐 **RESTful API** - Complete CRUD operations for editors
- 💾 **Database Management** - MySQL with optimized schemas
- 🔒 **Security Features** - Input sanitization, prepared statements
- 📊 **Advanced Queries** - Filtering, sorting, pagination
- 🎯 **Stored Procedures** - Optimized database operations
- 📈 **Performance Optimization** - Indexed queries and caching strategies

---

## 🛠 Technologies

### Frontend
- **HTML5** - Semantic markup
- **CSS3** - Modern styling with CSS Grid & Flexbox
- **JavaScript (ES6+)** - Interactive functionality
- **Google Fonts** - Orbitron & Inter typography

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL 5.7+** - Database management
- **PDO** - Database abstraction layer

### Development Tools
- Git - Version control
- VS Code - Recommended IDE
- XAMPP/WAMP - Local development environment

---

## 📁 Project Structure

```
frames/
│
├── index.html                 # Main HTML file
│
├── css/
│   └── style.css             # Main stylesheet
│
├── js/
│   ├── data.js               # Data configuration
│   └── main.js               # Main JavaScript logic
│
├── php/
│   └── config.php            # Database configuration
│
├── api/
│   └── editors.php           # Editors API endpoints
│
├── database/
│   └── schema.sql            # Database schema
│
├── uploads/                  # User uploaded files
│
├── assets/
│   ├── images/               # Static images
│   └── icons/                # Icon files
│
└── README.md                 # Project documentation
```

---

## 🚀 Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (optional, for dependencies)

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/frames.git
cd frames
```

### Step 2: Setup Database

1. Create a new MySQL database:
```sql
CREATE DATABASE frames_db;
```

2. Import the schema:
```bash
mysql -u root -p frames_db < database/schema.sql
```

### Step 3: Configure Database Connection

Edit `php/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'frames_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Step 4: Setup Web Server

#### Using XAMPP
1. Copy project folder to `C:/xampp/htdocs/frames`
2. Start Apache and MySQL from XAMPP Control Panel
3. Visit `http://localhost/frames`

#### Using PHP Built-in Server
```bash
cd frames
php -S localhost:8000
```
Visit `http://localhost:8000`

### Step 5: Create Uploads Directory

```bash
mkdir uploads
chmod 755 uploads
```

---

## ⚙️ Configuration

### Basic Settings

Edit `php/config.php` to customize:

```php
// Site Configuration
define('SITE_URL', 'http://localhost/frames');
define('SITE_NAME', 'FRAMES');

// Upload Settings
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB

// Timezone
date_default_timezone_set('America/Sao_Paulo');
```

### Security Settings

For production, update:

```php
// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

// Enable HTTPS cookies
ini_set('session.cookie_secure', 1);
```

---

## 💻 Usage

### Frontend Features

#### Search Functionality
```javascript
// Search for editors
document.getElementById('searchInput').value = 'Premiere';
// Results will be filtered automatically
```

#### Format Selection
```javascript
// Select a format category
document.querySelector('[data-format="gameplay"]').click();
```

#### Sidebar Filters
```javascript
// Toggle filters
document.querySelector('.toggle-switch').checked = true;
```

### API Usage

#### Get All Editors
```bash
GET /api/editors.php
```

#### Get Editor by ID
```bash
GET /api/editors.php?id=1
```

#### Search Editors
```bash
GET /api/editors.php?search=premiere&format=youtube
```

#### Create New Editor
```bash
POST /api/editors.php
Content-Type: application/json

{
  "name": "New Editor",
  "title": "Professional Videographer",
  "software": "Premiere Pro",
  "format": "youtube",
  "rating": 18,
  "featured": 1
}
```

#### Update Editor
```bash
PUT /api/editors.php
Content-Type: application/json

{
  "id": 1,
  "rating": 20,
  "reviews": 10
}
```

#### Delete Editor
```bash
DELETE /api/editors.php
Content-Type: application/json

{
  "id": 1
}
```

---

## 📚 API Documentation

### Editors Endpoint

**Base URL:** `/api/editors.php`

#### GET - List Editors

**Query Parameters:**
- `search` (string) - Search in name, title, software
- `format` (string) - Filter by format
- `software` (string) - Filter by software
- `featured` (boolean) - Filter featured editors
- `orderBy` (string) - Order by field (default: created_at)
- `orderDir` (string) - ASC or DESC (default: DESC)
- `page` (int) - Page number for pagination
- `perPage` (int) - Items per page (max 100)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "AURA FILMS",
      "title": "Aléx e Bibi Inuê",
      "software": "PR SUB",
      "rating": 16,
      "reviews": 3,
      "featured": 1
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 20,
    "total": 6,
    "totalPages": 1
  }
}
```

---

## 🗄️ Database Schema

### Main Tables

#### Users
- User authentication and profiles
- Roles: admin, editor, user

#### Editors
- Editor profiles and information
- Portfolio and statistics

#### Projects
- Client projects and assignments
- Status tracking and management

#### Reviews
- Editor ratings and feedback
- Connected to users and projects

#### Messages
- Internal messaging system
- Project-related communication

### Relationships

```
users → editors (1:many)
editors → projects (1:many)
users → projects (1:many)
editors → reviews (1:many)
projects → reviews (1:1)
```

---

## 🎨 Customization

### Colors

Edit CSS variables in `css/style.css`:

```css
:root {
    --primary-cyan: #00FFF0;
    --primary-purple: #9945FF;
    --dark-bg: #0a0a0f;
    --dark-card: #151520;
}
```

### Fonts

Change fonts in `index.html`:

```html
<link href="https://fonts.googleapis.com/css2?family=YourFont&display=swap" rel="stylesheet">
```

### Layout

Modify grid layout in `css/style.css`:

```css
.format-categories {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 2rem;
}
```

---

## 🔧 Development

### Adding New Features

1. **Frontend**: Add HTML structure → Style with CSS → Add JS functionality
2. **Backend**: Create API endpoint → Update database schema → Test with Postman
3. **Database**: Add table/column → Update API → Update frontend

### Testing

```bash
# Test API endpoints
curl -X GET http://localhost/frames/api/editors.php

# Test with parameters
curl -X GET "http://localhost/frames/api/editors.php?search=premiere"
```

---

## 🐛 Troubleshooting

### Common Issues

**Database Connection Error**
```
Solution: Check credentials in php/config.php
```

**404 on API Calls**
```
Solution: Enable mod_rewrite in Apache
```

**Upload Directory Not Writable**
```bash
chmod 755 uploads/
```

**JavaScript Not Loading**
```
Solution: Check browser console for errors
Clear cache and reload
```

---

## 📝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 👥 Authors

- **Your Name** - Initial work - [YourGitHub](https://github.com/yourusername)

---

## 🙏 Acknowledgments

- Design inspiration from modern video platforms
- Icons from Font Awesome and custom SVG
- Images from Unsplash
- Community feedback and contributions

---

## 📞 Support

For support, email support@frames.com or join our Slack channel.

---

## 🗺️ Roadmap

- [ ] User authentication system
- [ ] Payment integration
- [ ] Real-time chat functionality
- [ ] Video preview system
- [ ] Mobile app (React Native)
- [ ] Admin dashboard
- [ ] Email notifications
- [ ] Advanced analytics

---

**Made with ❤️ by the FRAMES Team**

*Last Updated: February 2026*
