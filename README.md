# Trading Platform - Full Stack Application

A real-time cryptocurrency trading platform built with Laravel and Vue.js, featuring live order matching, real-time updates via Pusher, and robust financial transaction handling.

## 🚀 Features

- **Real-time Order Matching**: Automatic order matching with price-time priority
- **Live Updates**: WebSocket-based real-time notifications via Pusher
- **Secure Transactions**: Database-level locking for race-condition prevention
- **Financial Integrity**: Precise decimal calculations with commission handling
- **Modern UI**: Responsive Vue.js interface with Tailwind CSS
- **Asset Management**: Multi-asset support (BTC, ETH) with locked amount tracking

## 🛠 Technology Stack

### Backend
- Laravel 11.x (PHP 8.2+)
- MySQL/PostgreSQL
- Laravel Sanctum (Authentication)
- Pusher (Real-time Broadcasting)
- Laravel Echo Server

### Frontend
- Vue.js 3.x (Composition API)
- Tailwind CSS 3.x
- Axios (HTTP Client)
- Pinia (State Management)
- Laravel Echo + Pusher JS

## 📋 Prerequisites

Before you begin, ensure you have installed:
- PHP >= 8.2
- Composer
- Node.js >= 18.x
- npm or yarn
- MySQL or PostgreSQL
- Git

## 🔧 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/trading-platform.git
cd trading-platform
```

### 2. Backend Setup

```bash
# Navigate to backend directory
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=trading_platform
# DB_USERNAME=root
# DB_PASSWORD=your_password

# Configure Pusher credentials in .env
# BROADCAST_CONNECTION=pusher
# PUSHER_APP_ID=your_app_id
# PUSHER_APP_KEY=your_app_key
# PUSHER_APP_SECRET=your_app_secret
# PUSHER_APP_CLUSTER=mt1

# Run migrations
php artisan migrate

# Seed the database with test data (optional)
php artisan db:seed

# Start the Laravel development server
php artisan serve
# Backend will run on http://localhost:8000
```

### 3. Frontend Setup

```bash
# Open a new terminal and navigate to frontend directory
cd frontend

# Install dependencies
npm install

# Create environment file
cp .env.example .env

# Configure API and Pusher settings in src/services/api.js
# Update baseURL to: http://localhost:8000/api

# Configure Pusher in src/services/echo.js
# Update with your Pusher credentials

# Start the development server
npm run dev
# Frontend will run on http://localhost:5173
```

## 🗄 Database Schema

### Users Table
- `id`: Primary key
- `name`: User's full name
- `email`: Unique email address
- `password`: Hashed password
- `balance`: USD balance (decimal 20,8)

### Assets Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `symbol`: Asset symbol (BTC, ETH)
- `amount`: Total asset amount (decimal 20,8)
- `locked_amount`: Amount locked in open orders (decimal 20,8)

### Orders Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `symbol`: Trading pair symbol
- `side`: Order side (buy/sell)
- `price`: Order price (decimal 20,8)
- `amount`: Order amount (decimal 20,8)
- `status`: Order status (1=open, 2=filled, 3=cancelled)

### Trades Table (Optional)
- `id`: Primary key
- `buy_order_id`: Foreign key to orders
- `sell_order_id`: Foreign key to orders
- `buyer_id`: Foreign key to users
- `seller_id`: Foreign key to users
- `symbol`: Trading pair
- `price`: Execution price
- `amount`: Traded amount
- `total`: Total transaction value
- `commission`: Commission charged (1.5%)

## 🔑 Default Test Accounts

After running `php artisan db:seed`, you can use:

| Email | Password | Initial Balance | Assets |
|-------|----------|-----------------|--------|
| user1@test.com | password | $100,000 | None |
| user2@test.com | password | $100,000 | 1 BTC, 10 ETH |

## 🎯 API Endpoints

### Authentication
- `POST /api/register` - Register new user
- `POST /api/login` - Login user
- `POST /api/logout` - Logout user (authenticated)

### Trading
- `GET /api/profile` - Get user profile and balances (authenticated)
- `GET /api/orders?symbol=BTC` - Get orderbook for symbol
- `POST /api/orders` - Place new order (authenticated)
- `POST /api/orders/{id}/cancel` - Cancel order (authenticated)
- `POST /api/orders/match` - Trigger order matching (authenticated)

## 🔄 Order Matching Logic

### Buy Order Flow
1. Verify user has sufficient USD balance
2. Deduct `amount * price` from user balance
3. Create order with status "open"
4. Search for matching sell orders where `sell.price <= buy.price`
5. Execute match if found

### Sell Order Flow
1. Verify user has sufficient asset amount
2. Move `amount` to `locked_amount` in assets table
3. Create order with status "open"
4. Search for matching buy orders where `buy.price >= sell.price`
5. Execute match if found

### Match Execution
1. Lock both orders and users for update
2. Calculate match price (older order's price)
3. Calculate commission (1.5% of total value)
4. Transfer USD from buyer to seller
5. Transfer assets from seller to buyer
6. Deduct commission from buyer
7. Update order statuses to "filled"
8. Create trade record
9. Broadcast real-time events to both parties

## 🔒 Security Features

- **Database Locking**: Prevents race conditions using `lockForUpdate()`
- **Atomic Transactions**: All financial operations wrapped in database transactions
- **Token Authentication**: Laravel Sanctum for secure API access
- **Input Validation**: Server-side validation for all requests
- **Private Channels**: User-specific WebSocket channels for sensitive data
- **CSRF Protection**: Built-in Laravel CSRF protection

## 🧪 Testing

### Manual Testing Steps

1. **Register/Login**: Create two user accounts
2. **Fund Accounts**: Use seeded data or manually add balance
3. **Place Buy Order**: User 1 places a buy order for BTC
4. **Place Sell Order**: User 2 places a matching sell order
5. **Verify Match**: Check that orders are matched and balances updated
6. **Real-time Check**: Verify both users receive instant notifications
7. **Cancel Order**: Test order cancellation and balance release

### Testing Scenarios

**Scenario 1: Successful Match**
```
User A: Buy 0.01 BTC @ $95,000
User B: Sell 0.01 BTC @ $94,000
Result: Match at $94,000 (seller's price)
```

**Scenario 2: Insufficient Balance**
```
User balance: $500
Order: Buy 0.01 BTC @ $95,000 (Total: $950)
Result: Order rejected
```

**Scenario 3: Order Cancellation**
```
User A: Buy 0.01 BTC @ $95,000 (locks $950)
User A: Cancel order
Result: $950 returned to balance
```

## 🐛 Troubleshooting

### Backend Issues

**Issue**: Migration fails
```bash
# Solution: Clear cache and retry
php artisan config:clear
php artisan cache:clear
php artisan migrate:fresh
```

**Issue**: Pusher not working
```bash
# Verify Pusher credentials in .env
# Check Laravel logs: storage/logs/laravel.log
# Test with Pusher debug console
```

### Frontend Issues

**Issue**: API requests fail
```bash
# Check CORS configuration in backend
# Verify API URL in src/services/api.js
# Check browser console for errors
```

**Issue**: Real-time updates not working
```bash
# Verify Pusher credentials match backend
# Check browser console for WebSocket errors
# Ensure broadcasting/auth endpoint is accessible
```

## 📁 Project Structure

```
trading-platform/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Events/         # Broadcast events
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   ├── Models/
│   │   └── Services/       # Business logic
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   ├── api.php
│   │   └── channels.php
│   └── .env.example
│
├── frontend/               # Vue.js Application
│   ├── src/
│   │   ├── assets/
│   │   ├── components/
│   │   ├── router/
│   │   ├── services/      # API & Echo services
│   │   ├── stores/        # Pinia stores
│   │   ├── views/
│   │   ├── App.vue
│   │   └── main.js
│   └── package.json
│
├── .gitignore
└── README.md
```

## 🚀 Deployment

### Backend Deployment (Production)

```bash
# Set production environment
APP_ENV=production
APP_DEBUG=false

# Install dependencies
composer install --optimize-autoloader --no-dev

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Set up queue worker for async jobs (optional)
php artisan queue:work --daemon
```

### Frontend Deployment

```bash
# Build for production
npm run build

# Deploy dist/ folder to your web server
# Configure web server to serve index.html for all routes
```

## 📝 Environment Variables

### Backend (.env)
```env
APP_NAME="Trading Platform"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trading_platform
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost
```

### Frontend (.env)
```env
VITE_API_URL=http://localhost:8000/api
VITE_PUSHER_KEY=your_pusher_key
VITE_PUSHER_CLUSTER=mt1
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License.

## 👥 Authors

- Your Name - Initial work

## 🙏 Acknowledgments

- Laravel framework team
- Vue.js core team
- Pusher for real-time infrastructure
- Tailwind CSS for styling utilities

## 📞 Support

For support, email support@tradingplatform.com or open an issue in the repository.
