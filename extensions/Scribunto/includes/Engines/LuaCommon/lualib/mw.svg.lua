local mwsvg = {}
local php

local util = require 'libraryUtil'
local checkType = util.checkType

-- Metatable for SVG objects
local svg_mt = {}
svg_mt.__index = svg_mt

local ALLOWED_IMG_ATTRIBUTES

local function makeSvgObject( data )
    data = data or {}

    local obj = {
        attributes = data.attributes or {},
        imgAttributes = data.imgAttributes or {},
        content = data.content or '',
    }

    setmetatable( obj, svg_mt )
    return obj
end

-- Constructor
function mwsvg.new()
    return makeSvgObject()
end

-- Set an SVG attribute
function svg_mt:setAttribute( name, value )
    checkType( 'setAttribute', 1, name, 'string' )
    checkType( 'setAttribute', 2, value, 'string' )

    -- Validate attribute name: must start with a-z, A-Z or underscore
    -- Can only include a-z, A-Z, 0-9, underscore (_), hyphen (-), period (.) and colon (:)
    if not name:match("^[a-zA-Z_][a-zA-Z0-9:%._%-]*$") then
        error( 'Invalid attribute name: ' .. name )
    end
    self.attributes[name] = value
    return self
end

-- Set an attribute in the img tag
function svg_mt:setImgAttribute( name, value )
    checkType( 'setImgAttribute', 1, name, 'string' )
    checkType( 'setImgAttribute', 2, value, 'string' )

    if not ALLOWED_IMG_ATTRIBUTES[name] then
        error( string.format( 'Attribute %s is not allowed for img tag', name ) )
    end

    self.imgAttributes[name] = value
    return self
end

-- Set the SVG content
function svg_mt:setContent( content )
    checkType( 'setContent', 1, content, 'string' )

    self.content = content
    return self
end

-- Generate the SVG as a string
function svg_mt:toString()
    return php.createSvgString( self.content, self.attributes )
end

-- Convert to an image tag with data URL
function svg_mt:toImage()
    return php.createImgTag( self.content, self.attributes, self.imgAttributes )
end

function mwsvg.setupInterface( opts )
    -- Boilerplate
    mwsvg.setupInterface = nil
    php = mw_interface
    mw_interface = nil

    -- Register this library in the "mw" global
    mw = mw or {}
    mw.svg = mwsvg

    -- Register constants
    ALLOWED_IMG_ATTRIBUTES = opts.ALLOWED_IMG_ATTRIBUTES

    package.loaded['mw.svg'] = mwsvg
end

return mwsvg
