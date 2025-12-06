local mwsvg = {}
local php

local util = require 'libraryUtil'
local checkType = util.checkType

-- Metatable for SVG objects
local svg_mt = {}
svg_mt.__index = svg_mt

local DEFAULT_NAMESPACE = 'http://www.w3.org/2000/svg'
local ALLOWED_IMG_ATTRIBUTES = {
    width = true,
    height = true,
    class = true,
    id = true,
    alt = true,
    title = true,
    style = true,
}

-- Escape XML special characters
local function escapeXml( value )
    local escapes = {
        ["<"] = "&lt;",
        [">"] = "&gt;",
        ["&"] = "&amp;",
        ['"'] = "&quot;",
    }
    return value:gsub( '[<>&"]', escapes )
end

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
    local output = '<svg'

    -- Add default SVG namespace if not provided
    if not self.attributes.xmlns then
        self.attributes.xmlns = DEFAULT_NAMESPACE
    end

    for name, value in pairs( self.attributes ) do
        output = output .. string.format( ' %s="%s"', name, escapeXml( value ) )
    end

    -- Add content and closing tag
    output = output .. '>' .. self.content .. '</svg>'

    return output
end

-- Convert to an image tag with data URL
function svg_mt:toImage()
    local svgString = self:toString()
    return php.createImgTag( svgString, self.imgAttributes )
end

function mwsvg.setupInterface()
    -- Boilerplate
    mwsvg.setupInterface = nil
    php = mw_interface
    mw_interface = nil

    -- Register this library in the "mw" global
    mw = mw or {}
    mw.svg = mwsvg

    package.loaded['mw.svg'] = mwsvg
end

return mwsvg
