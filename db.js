const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const bcrypt = require('bcrypt');

const dbPath = path.resolve(__dirname, 'medcare.db');

const db = new sqlite3.Database(dbPath, (err) => {
  if (err) {
    console.error('Error opening database', err.message);
  } else {
    console.log('Connected to the SQLite database.');
    
    // Create Tables
    db.serialize(() => {
      db.run(`CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        email TEXT UNIQUE,
        password_hash TEXT,
        role TEXT
      )`);

      db.run(`CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        icon TEXT,
        slug TEXT UNIQUE
      )`);

      db.run(`CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER,
        name TEXT,
        description TEXT,
        price REAL,
        rx_required INTEGER,
        image_url TEXT,
        stock INTEGER,
        FOREIGN KEY (category_id) REFERENCES categories (id)
      )`);

      db.run(`CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        tracking_id TEXT UNIQUE,
        total_amount REAL,
        status TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
      )`);

      db.run(`CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER,
        product_id INTEGER,
        quantity INTEGER,
        price REAL,
        FOREIGN KEY (order_id) REFERENCES orders (id),
        FOREIGN KEY (product_id) REFERENCES products (id)
      )`);

      db.run(`CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT,
        name TEXT,
        description TEXT,
        price REAL
      )`);

      // Insert Seed Data
      db.get('SELECT COUNT(*) as count FROM categories', [], (err, row) => {
        if (row && row.count === 0) {
          const insertCat = db.prepare('INSERT INTO categories (name, icon, slug) VALUES (?, ?, ?)');
          insertCat.run('OTC Medicines', 'pill', 'otc');
          insertCat.run('Prescription', 'prescription', 'rx');
          insertCat.run('Vitamins', 'bottle', 'vitamins');
          insertCat.finalize();

          const insertProd = db.prepare('INSERT INTO products (category_id, name, description, price, rx_required, image_url, stock) VALUES (?, ?, ?, ?, ?, ?, ?)');
          // Assuming 1: OTC, 2: Rx, 3: Vitamins
          insertProd.run(1, 'Paracetamol 500mg', 'Pain relief and fever reducer', 5.99, 0, 'https://via.placeholder.com/150/0B6E4F/ffffff?text=Paracetamol', 100);
          insertProd.run(1, 'Ibuprofen 400mg', 'Anti-inflammatory pain relief', 8.49, 0, 'https://via.placeholder.com/150/0B6E4F/ffffff?text=Ibuprofen', 50);
          insertProd.run(2, 'Amoxicillin 250mg', 'Antibiotic for bacterial infections', 15.00, 1, 'https://via.placeholder.com/150/FF6B35/ffffff?text=Amoxicillin', 30);
          insertProd.run(3, 'Vitamin C 1000mg', 'Immunity booster', 12.00, 0, 'https://via.placeholder.com/150/0B6E4F/ffffff?text=Vitamin+C', 200);
          insertProd.finalize();

          const insertSvc = db.prepare('INSERT INTO services (type, name, description, price) VALUES (?, ?, ?, ?)');
          insertSvc.run('lab_test', 'Complete Blood Count (CBC)', 'Comprehensive blood test', 49.99);
          insertSvc.run('consultation', 'General Physician Video Consult', '15 min online consultation', 29.99);
          insertSvc.finalize();
          
          console.log('Product/Category Seed data inserted.');
        }
      });

      // Seed Users
      db.get('SELECT COUNT(*) as count FROM users', [], (err, row) => {
        if (row && row.count === 0) {
          const insertUser = db.prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
          insertUser.run('Test Customer', 'customer@test.com', bcrypt.hashSync('customer123', 10), 'customer');
          insertUser.run('Test Retailer', 'retailer@test.com', bcrypt.hashSync('retailer123', 10), 'retailer');
          insertUser.finalize();
          console.log('User Seed data inserted (customer / retailer)');
        }
      });
    });
  }
});

module.exports = db;
