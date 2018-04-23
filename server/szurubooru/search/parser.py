import re
from szurubooru import errors
from szurubooru.search import criteria, tokens
from szurubooru.search.query import SearchQuery
from szurubooru.search.configs import util


def _create_criterion(
        original_value: str, value: str) -> criteria.BaseCriterion:
    if re.search(r'(?<!\\),', value):
        values = re.split(r'(?<!\\),', value)
        if any(not term.strip() for term in values):
            raise errors.SearchError('비어있는 복합 값')
        return criteria.ArrayCriterion(original_value, values)
    if re.search(r'(?<!\\)\.(?<!\\)\.', value):
        low, high = re.split(r'(?<!\\)\.(?<!\\)\.', value, 1)
        if not low and not high:
            raise errors.SearchError('비어있는 범위 값')
        return criteria.RangedCriterion(original_value, low, high)
    return criteria.PlainCriterion(original_value, value)


def _parse_anonymous(value: str, negated: bool) -> tokens.AnonymousToken:
    criterion = _create_criterion(value, value)
    return tokens.AnonymousToken(criterion, negated)


def _parse_named(key: str, value: str, negated: bool) -> tokens.NamedToken:
    original_value = value
    if key.endswith('-min'):
        key = key[:-4]
        value += '..'
    elif key.endswith('-max'):
        key = key[:-4]
        value = '..' + value
    criterion = _create_criterion(original_value, value)
    return tokens.NamedToken(key, criterion, negated)


def _parse_special(value: str, negated: bool) -> tokens.SpecialToken:
    return tokens.SpecialToken(value, negated)


def _parse_sort(value: str, negated: bool) -> tokens.SortToken:
    if value.count(',') == 0:
        order_str = None
    elif value.count(',') == 1:
        value, order_str = value.split(',')
    else:
        raise errors.SearchError('너무 많은 쉼표가 정렬 토큰에 사용됨.')
    try:
        order = {
            'asc': tokens.SortToken.SORT_ASC,
            'desc': tokens.SortToken.SORT_DESC,
            '': tokens.SortToken.SORT_DEFAULT,
            None: tokens.SortToken.SORT_DEFAULT,
        }[order_str]
    except KeyError:
        raise errors.SearchError(
            '알 수 없는 검색 방향: %r.' % order_str)
    if negated:
        order = {
            tokens.SortToken.SORT_ASC:
                tokens.SortToken.SORT_DESC,
            tokens.SortToken.SORT_DESC:
                tokens.SortToken.SORT_ASC,
            tokens.SortToken.SORT_DEFAULT:
                tokens.SortToken.SORT_NEGATED_DEFAULT,
            tokens.SortToken.SORT_NEGATED_DEFAULT:
                tokens.SortToken.SORT_DEFAULT,
        }[order]
    return tokens.SortToken(value, order)


class Parser:
    def parse(self, query_text: str) -> SearchQuery:
        query = SearchQuery()
        for chunk in re.split(r'\s+', (query_text or '').lower()):
            if not chunk:
                continue
            negated = False
            if chunk[0] == '-':
                chunk = chunk[1:]
                negated = True
            if not chunk:
                raise errors.SearchError('비어있는 부정 토큰.')
            match = re.match(r'^(.*?)(?<!\\):(.*)$', chunk)
            if match:
                key, value = list(match.groups())
                key = util.unescape(key)
                if key == 'sort':
                    query.sort_tokens.append(
                        _parse_sort(value, negated))
                elif key == 'special':
                    query.special_tokens.append(
                        _parse_special(value, negated))
                else:
                    query.named_tokens.append(
                        _parse_named(key, value, negated))
            else:
                query.anonymous_tokens.append(
                    _parse_anonymous(chunk, negated))
        return query
