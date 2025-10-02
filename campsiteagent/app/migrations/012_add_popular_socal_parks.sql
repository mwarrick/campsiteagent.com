-- Add popular Southern California state parks
-- Note: Parks are set to inactive by default until verified they have camping

-- Crystal Cove State Park (gorgeous coastal park near Newport Beach)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Crystal Cove SP', 'crystal_cove', '1244', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- Doheny State Beach (Dana Point - super popular for families)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Doheny SB', 'doheny', '639', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- Carlsbad State Beach (great beach camping in North County)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Carlsbad SB', 'carlsbad', '1105', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- San Elijo State Beach (Encinitas - bluff top camping)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('San Elijo SB', 'san_elijo', '709', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- South Carlsbad State Beach (family-friendly beach camping)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('South Carlsbad SB', 'south_carlsbad', '720', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- Leo Carrillo State Park (Malibu - beach and mountain camping)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Leo Carrillo SP', 'leo_carrillo', '665', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- Malibu Creek State Park (inland near Malibu - famous MASH site)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Malibu Creek SP', 'malibu_creek', '670', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- Point Mugu State Park (Ventura County - mountain and beach)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Point Mugu SP', 'point_mugu', '694', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- Refugio State Beach (Santa Barbara - beautiful coastal camping)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Refugio SB', 'refugio', '699', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

-- El Capitan State Beach (Santa Barbara - beach camping)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('El Capitan SB', 'el_capitan', '607', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number);

