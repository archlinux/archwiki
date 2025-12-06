local p = {}
local php
local options

function p.setupInterface( opts )
	-- Boilerplate
	p.setupInterface = nil
	php = mw_interface
	mw_interface = nil
	options = opts

	-- Loaded dynamically, don't mess with globals like 'mw' or
	-- 'package.loaded'
end

function p.message()
	return options.message
end

return p
