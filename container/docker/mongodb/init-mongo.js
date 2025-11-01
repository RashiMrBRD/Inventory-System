// MongoDB Initialization Script
// This script runs when the MongoDB container is first created

// Switch to the inventory_system database
db = db.getSiblingDB('inventory_system');

// Create application user with read/write access
db.createUser({
  user: process.env.MONGODB_USERNAME || 'inventory_user',
  pwd: process.env.MONGODB_PASSWORD || 'inventory_password',
  roles: [
    {
      role: 'readWrite',
      db: 'inventory_system'
    }
  ]
});

// Create collections with validation
db.createCollection('users', {
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['username', 'email', 'created_at'],
      properties: {
        username: {
          bsonType: 'string',
          description: 'Username must be a string and is required'
        },
        email: {
          bsonType: 'string',
          pattern: '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$',
          description: 'Email must be a valid email address and is required'
        },
        password: {
          bsonType: 'string',
          description: 'Password must be a string'
        },
        created_at: {
          bsonType: 'date',
          description: 'Creation date is required'
        }
      }
    }
  }
});

db.createCollection('items', {
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['name', 'quantity', 'created_at'],
      properties: {
        name: {
          bsonType: 'string',
          description: 'Item name must be a string and is required'
        },
        description: {
          bsonType: 'string',
          description: 'Item description'
        },
        quantity: {
          bsonType: 'int',
          minimum: 0,
          description: 'Quantity must be a positive integer'
        },
        price: {
          bsonType: ['double', 'int'],
          minimum: 0,
          description: 'Price must be a positive number'
        },
        created_at: {
          bsonType: 'date',
          description: 'Creation date is required'
        }
      }
    }
  }
});

db.createCollection('transactions');
db.createCollection('journal_entries');
db.createCollection('accounts');
db.createCollection('notifications');

// Create indexes for better performance
db.users.createIndex({ 'username': 1 }, { unique: true });
db.users.createIndex({ 'email': 1 }, { unique: true });
db.items.createIndex({ 'name': 1 });
db.items.createIndex({ 'created_at': -1 });
db.transactions.createIndex({ 'created_at': -1 });
db.journal_entries.createIndex({ 'date': -1 });
db.journal_entries.createIndex({ 'entry_number': 1 }, { unique: true });
db.accounts.createIndex({ 'account_code': 1 }, { unique: true });
db.notifications.createIndex({ 'user_id': 1, 'created_at': -1 });
db.notifications.createIndex({ 'read': 1, 'created_at': -1 });

// Insert default admin user (password should be hashed in production)
// Password: admin123 (please change this in production!)
db.users.insertOne({
  username: 'admin',
  email: 'admin@inventory.local',
  password: '$2y$12$z9UjPbZM/vGAESjnIIfRpuw7/F/3D0dtiEppSTVdUA1TXINDtCs.G', // admin123
  role: 'admin',
  created_at: new Date(),
  updated_at: new Date()
});

// Insert sample chart of accounts
const accounts = [
  { account_code: '1000', account_name: 'Cash', account_type: 'Asset', balance: 0 },
  { account_code: '1100', account_name: 'Accounts Receivable', account_type: 'Asset', balance: 0 },
  { account_code: '1200', account_name: 'Inventory', account_type: 'Asset', balance: 0 },
  { account_code: '2000', account_name: 'Accounts Payable', account_type: 'Liability', balance: 0 },
  { account_code: '3000', account_name: 'Capital', account_type: 'Equity', balance: 0 },
  { account_code: '4000', account_name: 'Sales Revenue', account_type: 'Revenue', balance: 0 },
  { account_code: '5000', account_name: 'Cost of Goods Sold', account_type: 'Expense', balance: 0 },
  { account_code: '6000', account_name: 'Operating Expenses', account_type: 'Expense', balance: 0 }
];

accounts.forEach(account => {
  db.accounts.insertOne({
    ...account,
    created_at: new Date(),
    updated_at: new Date()
  });
});

print('MongoDB initialization completed successfully!');
print('Database: inventory_system');
print('Collections created: users, items, transactions, journal_entries, accounts, notifications');
print('Indexes created for optimal performance');
print('Default admin user created (username: admin, password: admin123)');
print('IMPORTANT: Please change the default admin password in production!');
