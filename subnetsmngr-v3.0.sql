--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: customers; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
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


ALTER TABLE public.customers OWNER TO vcn;

--
-- Name: customers_id_seq; Type: SEQUENCE; Schema: public; Owner: vcn
--

CREATE SEQUENCE customers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.customers_id_seq OWNER TO vcn;

--
-- Name: customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: vcn
--

ALTER SEQUENCE customers_id_seq OWNED BY customers.id;


--
-- Name: group_subnets; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
--

CREATE TABLE group_subnets (
    group_id integer NOT NULL,
    subnet_id integer NOT NULL
);


ALTER TABLE public.group_subnets OWNER TO vcn;

--
-- Name: groups; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
--

CREATE TABLE groups (
    id integer NOT NULL,
    name character varying(255),
    lat double precision,
    lng double precision
);


ALTER TABLE public.groups OWNER TO vcn;

--
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: vcn
--

CREATE SEQUENCE groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.groups_id_seq OWNER TO vcn;

--
-- Name: groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: vcn
--

ALTER SEQUENCE groups_id_seq OWNED BY groups.id;


--
-- Name: hosts; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
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
    last_updated timestamp without time zone,
    config text,
    created_user_id integer,
    created timestamp without time zone
);


ALTER TABLE public.hosts OWNER TO vcn;

--
-- Name: hosts_id_seq; Type: SEQUENCE; Schema: public; Owner: vcn
--

CREATE SEQUENCE hosts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.hosts_id_seq OWNER TO vcn;

--
-- Name: hosts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: vcn
--

ALTER SEQUENCE hosts_id_seq OWNED BY hosts.id;


--
-- Name: hosts_pending; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
--

CREATE TABLE hosts_pending (
    subnet_id integer,
    addr inet,
    added timestamp without time zone
);


ALTER TABLE public.hosts_pending OWNER TO vcn;

--
-- Name: instances; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
--

CREATE TABLE instances (
    id integer NOT NULL,
    name character varying(32)
);


ALTER TABLE public.instances OWNER TO vcn;

--
-- Name: instances_id_seq; Type: SEQUENCE; Schema: public; Owner: vcn
--

CREATE SEQUENCE instances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.instances_id_seq OWNER TO vcn;

--
-- Name: instances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: vcn
--

ALTER SEQUENCE instances_id_seq OWNED BY instances.id;


--
-- Name: subnets; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
--

CREATE TABLE subnets (
    id integer NOT NULL,
    customer_id integer,
    addr inet NOT NULL,
    free smallint NOT NULL,
    swiped smallint DEFAULT 0 NOT NULL,
    last_swiped timestamp without time zone,
    description text,
    notes text,
    last_updated_user_id integer NOT NULL,
    last_updated timestamp without time zone,
    noautoalloc smallint DEFAULT 0,
    parent_id smallint DEFAULT 0 NOT NULL,
    instance_id integer,
    vlan integer,
    created timestamp without time zone,
    created_user_id integer,
    migrating_to_subnet_id integer
);


ALTER TABLE public.subnets OWNER TO vcn;

--
-- Name: subnets_id_seq; Type: SEQUENCE; Schema: public; Owner: vcn
--

CREATE SEQUENCE subnets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.subnets_id_seq OWNER TO vcn;

--
-- Name: subnets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: vcn
--

ALTER SEQUENCE subnets_id_seq OWNED BY subnets.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: vcn; Tablespace: 
--

CREATE TABLE users (
    id integer NOT NULL,
    username character varying(64) NOT NULL,
    password character varying(32) NOT NULL,
    name character varying(64),
    lastlogin timestamp without time zone,
    default_instance_id integer,
    lastip character varying(45) DEFAULT NULL::character varying,
    last_lat double precision,
    last_lng double precision,
    last_loc_time timestamp without time zone
);


ALTER TABLE public.users OWNER TO vcn;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: vcn
--

CREATE SEQUENCE users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE public.users_id_seq OWNER TO vcn;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: vcn
--

ALTER SEQUENCE users_id_seq OWNED BY users.id;


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY customers ALTER COLUMN id SET DEFAULT nextval('customers_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY groups ALTER COLUMN id SET DEFAULT nextval('groups_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY hosts ALTER COLUMN id SET DEFAULT nextval('hosts_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY instances ALTER COLUMN id SET DEFAULT nextval('instances_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY subnets ALTER COLUMN id SET DEFAULT nextval('subnets_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY users ALTER COLUMN id SET DEFAULT nextval('users_id_seq'::regclass);


--
-- Name: customers_pkey; Type: CONSTRAINT; Schema: public; Owner: vcn; Tablespace: 
--

ALTER TABLE ONLY customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: groups_pkey; Type: CONSTRAINT; Schema: public; Owner: vcn; Tablespace: 
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- Name: hosts_pkey; Type: CONSTRAINT; Schema: public; Owner: vcn; Tablespace: 
--

ALTER TABLE ONLY hosts
    ADD CONSTRAINT hosts_pkey PRIMARY KEY (id);


--
-- Name: subnets_pkey; Type: CONSTRAINT; Schema: public; Owner: vcn; Tablespace: 
--

ALTER TABLE ONLY subnets
    ADD CONSTRAINT subnets_pkey PRIMARY KEY (id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: vcn; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: created_user_id_idx; Type: INDEX; Schema: public; Owner: vcn; Tablespace: 
--

CREATE INDEX created_user_id_idx ON subnets USING btree (created_user_id);


--
-- Name: group_subnets_group_id_idx; Type: INDEX; Schema: public; Owner: vcn; Tablespace: 
--

CREATE INDEX group_subnets_group_id_idx ON group_subnets USING btree (group_id);


--
-- Name: group_subnets_subnet_id_idx; Type: INDEX; Schema: public; Owner: vcn; Tablespace: 
--

CREATE INDEX group_subnets_subnet_id_idx ON group_subnets USING btree (subnet_id);


--
-- Name: hosts_last_updated_user_id_idx; Type: INDEX; Schema: public; Owner: vcn; Tablespace: 
--

CREATE INDEX hosts_last_updated_user_id_idx ON hosts USING btree (last_updated_user_id);


--
-- Name: subnets_customer_id_idx; Type: INDEX; Schema: public; Owner: vcn; Tablespace: 
--

CREATE INDEX subnets_customer_id_idx ON subnets USING btree (customer_id);


--
-- Name: subnets_last_updated_user_id_idx; Type: INDEX; Schema: public; Owner: vcn; Tablespace: 
--

CREATE INDEX subnets_last_updated_user_id_idx ON subnets USING btree (last_updated_user_id);


--
-- Name: hosts_pending_subnet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY hosts_pending
    ADD CONSTRAINT hosts_pending_subnet_id_fkey FOREIGN KEY (subnet_id) REFERENCES subnets(id);


--
-- Name: hosts_subnet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: vcn
--

ALTER TABLE ONLY hosts
    ADD CONSTRAINT hosts_subnet_id_fkey FOREIGN KEY (subnet_id) REFERENCES subnets(id) ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

