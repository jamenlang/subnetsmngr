--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: customers; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE customers (
    id integer NOT NULL,
    name character varying(255),
    addr1 character varying(255),
    addr2 character varying(255),
    city character varying(128),
    state character varying(2),
    zip character varying(10),
    country character varying(2)
);


--
-- Name: customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE customers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE customers_id_seq OWNED BY customers.id;


--
-- Name: group_subnets; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE group_subnets (
    group_id integer NOT NULL,
    subnet_id integer NOT NULL
);


--
-- Name: groups; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE groups (
    id integer NOT NULL,
    name character varying(255)
);


--
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE groups_id_seq OWNED BY groups.id;


--
-- Name: hosts; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE hosts (
    id integer NOT NULL,
    subnet_id integer NOT NULL,
    addr inet NOT NULL,
    free smallint NOT NULL,
    gateway smallint NOT NULL,
    description text,
    notes text,
    last_updated_user_id integer NOT NULL,
    last_updated timestamp without time zone
);


--
-- Name: hosts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE hosts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: hosts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE hosts_id_seq OWNED BY hosts.id;


--
-- Name: subnets; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE subnets (
    id integer NOT NULL,
    parent_id integer NOT NULL,
    customer_id integer,
    addr inet NOT NULL,
    free smallint NOT NULL,
    swiped smallint DEFAULT 0 NOT NULL,
    last_swiped timestamp without time zone,
    description text,
    notes text,
    last_updated_user_id integer NOT NULL,
    last_updated timestamp without time zone,
    noautoalloc smallint DEFAULT 0 NOT NULL
);


--
-- Name: subnets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE subnets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: subnets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE subnets_id_seq OWNED BY subnets.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE users (
    id integer NOT NULL,
    username character varying(64) NOT NULL,
    password character varying(32) NOT NULL,
    name character varying(64),
    lastlogin timestamp without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE users_id_seq OWNED BY users.id;


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE customers ALTER COLUMN id SET DEFAULT nextval('customers_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE groups ALTER COLUMN id SET DEFAULT nextval('groups_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE hosts ALTER COLUMN id SET DEFAULT nextval('hosts_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE subnets ALTER COLUMN id SET DEFAULT nextval('subnets_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval('users_id_seq'::regclass);


--
-- Name: customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- Name: hosts_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY hosts
    ADD CONSTRAINT hosts_pkey PRIMARY KEY (id);


--
-- Name: subnets_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY subnets
    ADD CONSTRAINT subnets_pkey PRIMARY KEY (id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: group_subnets_group_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX group_subnets_group_id_idx ON group_subnets USING btree (group_id);


--
-- Name: group_subnets_subnet_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX group_subnets_subnet_id_idx ON group_subnets USING btree (subnet_id);


--
-- Name: hosts_last_updated_user_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX hosts_last_updated_user_id_idx ON hosts USING btree (last_updated_user_id);


--
-- Name: subnets_customer_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX subnets_customer_id_idx ON subnets USING btree (customer_id);


--
-- Name: subnets_last_updated_user_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX subnets_last_updated_user_id_idx ON subnets USING btree (last_updated_user_id);


--
-- Name: subnets_parent_id_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX subnets_parent_id_idx ON subnets USING btree (parent_id);


--
-- Name: hosts_subnet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY hosts
    ADD CONSTRAINT hosts_subnet_id_fkey FOREIGN KEY (subnet_id) REFERENCES subnets(id) ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: -
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

