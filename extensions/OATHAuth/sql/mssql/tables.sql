CREATE TABLE /*_*/oathauth_users (
	-- User ID
	id INT NOT NULL PRIMARY KEY IDENTITY(0,1),

	-- Module user has selected
	module NVARCHAR(255) NOT NULL,

	-- Module data
	data VARBINARY(MAX) NULL DEFAULT NULL
);
