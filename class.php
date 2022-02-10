<?php
namespace Addons;
class required_nickname
{
	private $nick_name = null;
	private static $config = null;
	
	public function __construct()
	{
		$this->setNickname(\Context::get('nick_name'));
		if(self::$config === null || !$this->nick_name)
		{
			return;
		}
		if(!self::$config->is_check_existing && \Context::get('is_logged') && $this->nick_name === \Context::get('logged_info')->nick_name)
		{
			return;
		}
		
		// Check
		$this->checkCharacter();
		$this->checkLength();
		$this->checkWord();
	}
	
	public static function setConfig($addon_info)
	{
		self::$config = new \stdClass;
		self::$config->min_length = (int)($addon_info->min_length ?? 0);
		self::$config->max_length = (int)($addon_info->max_length ?? 0);
		self::$config->is_mixed_use = tobool($addon_info->mixed_use ?? 'Y');
		self::$config->is_allow_hangul = tobool($addon_info->allow_hangul ?? 'Y');
		self::$config->is_allow_lowercase = tobool($addon_info->allow_lowercase ?? 'Y');
		self::$config->is_allow_uppercase = tobool($addon_info->allow_uppercase ?? 'Y');
		self::$config->is_double_hangul = tobool($addon_info->double_hangul ?? 'N');
		self::$config->is_check_word = tobool($addon_info->check_word ?? 'N');
		self::$config->opendict_api_key = $addon_info->opendict_api_key ?? null;
		self::$config->is_check_existing = tobool($addon_info->check_existing ?? 'N');
	}
	
	public function setNickname($nick_name)
	{
		$this->nick_name = $nick_name;
	}
	
	public function checkCharacter()
	{
		$allow_characters = array();
		$allow_character_names = array();
		
		// Allow hangul
		if(self::$config->is_allow_hangul)
		{
			$allow_characters[] = '가-힣';
			$allow_character_names[] = '한글';
			
			if(preg_match('/[ㄱ-ㅎㅏ-ㅣ]/u', $this->nick_name))
			{
				throw new \Rhymix\Framework\Exception('한글 조합이 안된 자모음은 닉네임으로 사용할 수 없습니다.');
			}
			if(!self::$config->is_double_hangul && self::isDoubleHangul($this->nick_name))
			{
				throw new \Rhymix\Framework\Exception('사용할 수 없는 닉네임입니다.');
			}
		}
		
		// Allow english
		if(self::$config->is_allow_lowercase && self::$config->is_allow_uppercase)
		{
			$allow_characters[] = 'a-zA-Z';
			$allow_character_names[] = '영문';
		}
		// Allow lowercase
		elseif(self::$config->is_allow_lowercase)
		{
			$allow_characters[] = 'a-z';
			$allow_character_names[] = '영소문자';
		}
		// Allow uppercase
		elseif(self::$config->is_allow_uppercase)
		{
			$allow_characters[] = 'A-Z';
			$allow_character_names[] = '영대문자';
		}
		
		// Check character
		$allow_character_name = implode('/', $allow_character_names);
		if(!self::$config->is_mixed_use && count($allow_characters) > 1)
		{
			if(!preg_match(sprintf('/^(?:[%s]+)$/u', implode(']+|[', $allow_characters)), $this->nick_name))
			{
				throw new \Rhymix\Framework\Exception(sprintf('닉네임은 %s 중 하나로만 이루어져야 합니다. (혼용불가)', $allow_character_name));
			}
		}
		else
		{
			if(preg_match(sprintf('/[^%s]/u', implode('', $allow_characters)), $this->nick_name))
			{
				throw new \Rhymix\Framework\Exception(sprintf('닉네임은 %s만 가능합니다.', $allow_character_name));
			}
		}
	}
	
	public function checkLength()
	{
		$name_length = mb_strwidth($this->nick_name, 'UTF-8');
		
		// Perceive a uppercase as 1.5
		if($uppercase = preg_match_all('/[A-Z]/', $this->nick_name))
		{
			$name_length += $uppercase * 0.5;
		}
		
		// Check length
		if(preg_match('/^[가-힣]+$/u', $this->nick_name))
		{
			if(self::$config->min_length > 0 && $name_length < self::$config->min_length)
			{
				throw new \Rhymix\Framework\Exception(sprintf('닉네임의 길이는 최소 %d자 이상이어야 합니다.', (int)(self::$config->min_length / 2)));
			}
			if(self::$config->max_length > 0 && $name_length > self::$config->max_length)
			{
				throw new \Rhymix\Framework\Exception(sprintf('닉네임의 길이는 최대 %d자 이하여야 합니다.', (int)(self::$config->max_length / 2)));
			}
		}
		else
		{
			if(self::$config->min_length > 0 && $name_length < self::$config->min_length)
			{
				throw new \Rhymix\Framework\Exception(sprintf('닉네임의 길이는 (소문자 기준) 최소 %d 이상이어야 합니다.', self::$config->min_length));
			}
			if(self::$config->max_length > 0 && $name_length > self::$config->max_length)
			{
				throw new \Rhymix\Framework\Exception(sprintf('닉네임의 길이는 (소문자 기준) 최대 %d 이하여야 합니다.', self::$config->max_length));
			}
		}
	}
	
	public function checkWord()
	{
		if(!self::$config->is_check_word)
		{
			return;
		}
		if(\Context::get('is_logged') && $this->nick_name === \Context::get('logged_info')->nick_name)
		{
			return;
		}
		
		// Ambiguous word
		if(preg_match(sprintf('/(?:%s)s?$/iu', implode('|', self::getAmbiguousWord())), $this->nick_name))
		{
			throw new \Rhymix\Framework\Exception('사용할 수 없는 닉네임입니다.');
		}
		
		// Dictionary
		if(self::$config->opendict_api_key && preg_match('/^[가-힣]+$/u', $this->nick_name))
		{
			$dictionary_url = 'https://opendict.korean.go.kr/api/search';
			$params = array(
				'key' => self::$config->opendict_api_key,
				'q' => $this->nick_name,
				'part' => 'word',
				'sort' => 'dict',
				'start' => '1',
				'num' => '10',
			);
			if(!($dictionary = self::requestAPI($dictionary_url, $params)) || isset($dictionary->error))
			{
				throw new \Rhymix\Framework\Exception('일시적인 오류입니다. 잠시후 다시 시도해주세요.');
			}
			if(!empty($dictionary->channel->total->body) && isset($dictionary->channel->item))
			{
				if(!is_array($dictionary->channel->item))
				{
					$dictionary->channel->item = array($dictionary->channel->item);
				}
				foreach($dictionary->channel->item as $item)
				{
					if(in_array(str_replace(array('-', '^'), '', $item->word->body), [$this->nick_name, $this->nick_name . '님']))
					{
						throw new \Rhymix\Framework\Exception('사용할 수 없는 닉네임입니다.');
					}
				}
			}
		}
	}
	
	private static function requestAPI($url, $params = array())
	{
		$buff = \FileHandler::getRemoteResource(
				$url . '?' . http_build_query($params, '', '&'),
				null,
				3,
				'GET',
				'application/x-www-form-urlencoded',
				array(),
				array(),
				array(),
				array('verify' => false)
		);
		if(!$buff)
		{
			return;
		}
		return (new \XeXmlParser())->parse($buff);
	}
	
	private static function isDoubleHangul($string)
	{
		if(!preg_match('/[가-힣]/u', $string))
		{
			return false;
		}
		
		$string = mb_convert_encoding($string, 'UTF-16BE', 'UTF-8');
		$characters = str_split($string);
		
		// phoneme
		$phoneme = [
			'cho' => ['ㄱ', 'ㄲ', 'ㄴ', 'ㄷ', 'ㄸ', 'ㄹ', 'ㅁ', 'ㅂ', 'ㅃ', 'ㅅ', 'ㅆ', 'ㅇ', 'ㅈ', 'ㅉ', 'ㅊ', 'ㅋ', 'ㅌ', 'ㅍ', 'ㅎ'],
			'jung' => ['ㅏ', 'ㅐ', 'ㅑ', 'ㅒ', 'ㅓ', 'ㅔ', 'ㅕ', 'ㅖ', 'ㅗ', 'ㅘ', 'ㅙ', 'ㅚ', 'ㅛ', 'ㅜ', 'ㅝ', 'ㅞ', 'ㅟ', 'ㅠ', 'ㅡ', 'ㅢ', 'ㅣ'],
			'jong' => ['', 'ㄱ', 'ㄲ', 'ㄳ', 'ㄴ', 'ㄵ', 'ㄶ', 'ㄷ', 'ㄹ', 'ㄺ', 'ㄻ', 'ㄼ', 'ㄽ', 'ㄾ', 'ㄿ', 'ㅀ', 'ㅁ', 'ㅂ', 'ㅄ', 'ㅅ', 'ㅆ', 'ㅇ', 'ㅈ', 'ㅊ', 'ㅋ', 'ㅌ', 'ㅍ', 'ㅎ'],
		];
		$double = [
			'cho' => ['ㄲ', 'ㄸ', 'ㅃ', 'ㅆ', 'ㅉ'],
			'jung' => ['ㅑ', 'ㅒ', 'ㅕ', 'ㅖ', 'ㅘ', 'ㅙ', 'ㅚ', 'ㅛ', 'ㅝ', 'ㅞ', 'ㅟ', 'ㅠ', 'ㅢ'],
			'jong' => ['ㄲ', 'ㄳ', 'ㄵ', 'ㄶ', 'ㄺ', 'ㄻ', 'ㄼ', 'ㄽ', 'ㄾ', 'ㄿ', 'ㅀ', 'ㅄ', 'ㅆ', 'ㅈ', 'ㅊ', 'ㅋ', 'ㅌ', 'ㅍ', 'ㅎ'],
		];
		
		for($i = 0; $i < count($characters); $i++)
		{
			$unicode = (ord($characters[$i]) * 256) + ord($characters[$i + 1]);
			if($unicode < 44032 || $unicode > 55203)
			{
				continue;
			}
			
			// hangul separation
			$code = $unicode - 44032;
			$char = [
				'cho' => $phoneme['cho'][(int)($code / count($phoneme['jong']) / count($phoneme['jung']))],
				'jung' => $phoneme['jung'][$code / count($phoneme['jong']) % count($phoneme['jung'])],
				'jong' => $phoneme['jong'][$code % count($phoneme['jong'])],
			];
			
			// 겹 종성 ex. ㄼ
			if(in_array($char['jong'], $double['jong']))
			{
				return true;
			}
			// 겹 중성 ex. ㅢ
			if(in_array($char['jung'], $double['jung']))
			{
				// 겹 초성과 함께 ex. 끠
				if(in_array($char['cho'], $double['cho']))
				{
					return true;
				}
				// 종성과 함께 ex. 긥
				if($char['jong'])
				{
					//return true;
				}
			}
			
			$i++;
		}
		
		return false;
	}
	
	private static function getAmbiguousWord()
	{
		return [
			'관리자', '관리인', 'admin', 'administrator', '매니저', 'manager', '운영자', '운영인', '운영진', '운영팀', '중재자', 'moderator', '담당자',
			'닉네임', '회원', '질문자', '게시자', '작성자', '글쓴이', '익명', '선생', '스승', '서방', '선배', '후배', '아우', '천주', '생원', '형수', '형제', '자매', '고객', '도련', '공주', '왕자', '백작', '영감', '대감', '상감', '선비', '나라', '나랏', '하나', '하느', '아버', '이모', '고모', '부모', '어머', '할머', '장', '사', '스', '주', '님', '손', '마', '누', '형', '별', '헹', '행', '부', '녀', '관', '령', '신', '자', '가',
		];
	}
}