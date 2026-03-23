-- Database Optimization Recommendations for Marvin Intranet

-- Add these indexes to improve query performance



-- Indexes for bulletin_posts table

CREATE INDEX idx_bulletin_posts_user_id ON bulletin_posts(user_id);

CREATE INDEX idx_bulletin_posts_created_at ON bulletin_posts(created_at);

CREATE INDEX idx_bulletin_posts_pinned_created ON bulletin_posts(pinned, created_at DESC);



-- Indexes for chat_sessions table

CREATE INDEX idx_chat_sessions_user_id ON chat_sessions(user_id);

CREATE INDEX idx_chat_sessions_created_at ON chat_sessions(created_at);

CREATE INDEX idx_chat_sessions_updated_at ON chat_sessions(updated_at);



-- Indexes for chat_messages table

CREATE INDEX idx_chat_messages_session_id ON chat_messages(session_id);

CREATE INDEX idx_chat_messages_timestamp ON chat_messages(timestamp);



-- Indexes for users table

CREATE INDEX idx_users_email ON users(email);

CREATE INDEX idx_users_role ON users(role);

CREATE INDEX idx_users_created_at ON users(created_at);



-- Optimize existing queries by analyzing execution plans

-- Run these commands to analyze query performance:

-- EXPLAIN SELECT * FROM bulletin_posts LEFT JOIN users ON bulletin_posts.user_id = users.id ORDER BY pinned DESC, created_at DESC LIMIT 3;

-- SHOW INDEX FROM bulletin_posts;

