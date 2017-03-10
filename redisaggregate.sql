CREATE TABLE goods (
	id INT NOT NULL,
	name varchar(255) NOT NULL,
	providers_id INT NOT NULL,
	units_id INT NOT NULL,
	price DECIMAL NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE units (
	id INT NOT NULL,
	name varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE providers (
	id INT NOT NULL,
	name varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE sales (
	id int NOT NULL,
	bills_id INT NOT NULL,
	goods_id int NOT NULL,
	quantity DECIMAL NOT NULL,
	sum DECIMAL NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE bills (
	id INT NOT NULL,
	dt DATE NOT NULL,
	PRIMARY KEY (id)
);

ALTER TABLE goods ADD CONSTRAINT goods_fk0 FOREIGN KEY (providers_id) REFERENCES providers(id);

ALTER TABLE goods ADD CONSTRAINT goods_fk1 FOREIGN KEY (units_id) REFERENCES units(id);

ALTER TABLE sales ADD CONSTRAINT sales_fk0 FOREIGN KEY (bills_id) REFERENCES bills(id);

ALTER TABLE sales ADD CONSTRAINT sales_fk1 FOREIGN KEY (goods_id) REFERENCES goods(id);

CREATE INDEX sales_bills_id ON sales(bills_id);

CREATE INDEX sales_goods_id ON sales(goods_id);

CREATE INDEX goods_providers_id ON goods(providers_id);

begin
 	dbms_stats.gather_schema_stats(ownname => 'ch', estimate_percent => DBMS_STATS.AUTO_SAMPLE_SIZE, cascade=>TRUE);
end;


