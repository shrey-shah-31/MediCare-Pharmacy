const express = require('express');
const cors = require('cors');
const db = require('./db');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');

const app = express();
app.use(cors());
app.use(express.json());

const PORT = 3000;
const JWT_SECRET = 'medcare-secret-key'; // In prod, use environment variable

// Middleware to verify JWT
const authenticate = (req, res, next) => {
  const authHeader = req.headers.authorization;
  if (authHeader) {
    const token = authHeader.split(' ')[1];
    jwt.verify(token, JWT_SECRET, (err, user) => {
      if (err) return res.sendStatus(403);
      req.user = user;
      next();
    });
  } else {
    res.sendStatus(401);
  }
};

// Middleware to authorize roles
const authorize = (roles) => {
  return (req, res, next) => {
    if (!req.user || !roles.includes(req.user.role)) {
      return res.status(403).json({ error: 'Forbidden: Insufficient permissions' });
    }
    next();
  };
};

// ---------------------------------------------------------
// AUTH ROUTES
// ---------------------------------------------------------
app.post('/api/auth/register', async (req, res) => {
  const { name, email, password, role = 'customer' } = req.body;
  if (!name || !email || !password) {
    return res.status(400).json({ error: 'Name, email, and password are required' });
  }

  try {
    const hashedPassword = await bcrypt.hash(password, 10);
    db.run(`INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)`,
      [name, email, hashedPassword, role],
      function(err) {
        if (err) {
          if (err.message.includes('UNIQUE constraint failed')) {
            return res.status(409).json({ error: 'Email already exists' });
          }
          return res.status(500).json({ error: err.message });
        }
        const token = jwt.sign({ id: this.lastID, email, role }, JWT_SECRET, { expiresIn: '1d' });
        res.status(201).json({ token, user: { id: this.lastID, name, email, role } });
      }
    );
  } catch (error) {
    res.status(500).json({ error: 'Server error during registration' });
  }
});

app.post('/api/auth/login', (req, res) => {
  const { email, password } = req.body;
  if (!email || !password) {
    return res.status(400).json({ error: 'Email and password are required' });
  }

  db.get(`SELECT * FROM users WHERE email = ?`, [email], async (err, user) => {
    if (err) return res.status(500).json({ error: err.message });
    if (!user) return res.status(401).json({ error: 'Invalid email or password' });

    const passwordMatch = await bcrypt.compare(password, user.password_hash);
    if (!passwordMatch) return res.status(401).json({ error: 'Invalid email or password' });

    const token = jwt.sign({ id: user.id, email: user.email, role: user.role }, JWT_SECRET, { expiresIn: '1d' });
    res.json({ token, user: { id: user.id, name: user.name, email: user.email, role: user.role } });
  });
});

// ---------------------------------------------------------
// PRODUCT ROUTES
// ---------------------------------------------------------
app.get('/api/categories', (req, res) => {
  db.all('SELECT * FROM categories', [], (err, rows) => {
    if (err) return res.status(500).json({ error: err.message });
    res.json(rows);
  });
});

app.get('/api/products', (req, res) => {
  db.all('SELECT * FROM products', [], (err, rows) => {
    if (err) return res.status(500).json({ error: err.message });
    res.json(rows);
  });
});

app.get('/api/products/:id', (req, res) => {
  db.get('SELECT * FROM products WHERE id = ?', [req.params.id], (err, row) => {
    if (err) return res.status(500).json({ error: err.message });
    if (!row) return res.status(404).json({ error: 'Not found' });
    res.json(row);
  });
});

// ---------------------------------------------------------
// ORDER ROUTES
// ---------------------------------------------------------
app.post('/api/orders', authenticate, authorize(['customer']), (req, res) => {
  const { items, total_amount } = req.body;
  const tracking_id = `TRK-${new Date().toISOString().slice(0,10).replace(/-/g,'')}-${Math.random().toString(36).substr(2, 8).toUpperCase()}`;

  db.run(`INSERT INTO orders (user_id, tracking_id, total_amount, status) VALUES (?, ?, ?, ?)`,
    [req.user.id, tracking_id, total_amount, 'processing'],
    function(err) {
      if (err) return res.status(500).json({ error: err.message });
      const orderId = this.lastID;
      
      const placeholders = items.map(() => '(?, ?, ?, ?)').join(',');
      const values = items.flatMap(item => [orderId, item.product_id, item.quantity, item.price]);
      
      db.run(`INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ${placeholders}`, values, (err) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json({ orderId, tracking_id, status: 'success' });
      });
    }
  );
});

app.get('/api/orders', authenticate, authorize(['customer']), (req, res) => {
  db.all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', [req.user.id], (err, rows) => {
    if (err) return res.status(500).json({ error: err.message });
    res.json(rows);
  });
});

app.listen(PORT, () => {
  console.log(`MedCare Backend running on http://localhost:${PORT}`);
});
