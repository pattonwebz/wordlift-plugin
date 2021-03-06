/* globals wp, wlSettings */
/**
 * External dependencies
 */
import React from "react";
import styled from "styled-components";

const { ClipboardButton } = wp.components;
const { withState } = wp.compose;

const Image = styled.img`
  width: 80px;
  margin: 0 0.5rem 0 0;
`;

const Heading = styled.h5`
  margin: 0 0 0.5rem 0;
`;

const LinkClipboardButton = withState({
  hasCopied: false
})(({ hasCopied, setState, ...props }) => (
  <ClipboardButton
    {...props}
    onCopy={() => setState({ hasCopied: true })}
    onFinishCopy={() => setState({ hasCopied: false })}
  >
    {hasCopied ? "Link Copied!" : "Copy Link"}
  </ClipboardButton>
));

const Post = props => (
  <React.Fragment>
    <Image src={props.thumbnail} />
    <div>
      <Heading>{props.post_title}</Heading>
      <LinkClipboardButton text={props.permalink} isSmall={true} isDefault={true} />
    </div>
  </React.Fragment>
);

export default Post;
